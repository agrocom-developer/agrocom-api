<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenTrabajoNoEditable;
use App\Dominios\Operaciones\Dominio\Excepciones\TarifaNoDisponible;
use App\Dominios\Operaciones\Dominio\PoliticaEdicionOrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajoEquipo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Edición de una Orden de Trabajo (tarea 127): la cabecera (indicaciones
 * compartidas — calda, límites climáticos, parámetros de vuelo) y, por
 * equipo, su condición de pago (ADR 0023). El reparto (equipos, lotes,
 * hectáreas, turno) NO se toca acá — eso sigue siendo por `Trabajo`, desde
 * `Aplicacion/ActualizarTrabajo`.
 *
 * Las restricciones viven ACÁ, no solo en la vista (invariante 7 de
 * CLAUDE.md): un `PUT` directo las respeta igual. La fila se relee con
 * `lockForUpdate()` para no editar sobre un estado que otro operador acaba
 * de cambiar (una sesión que se validó mientras el formulario estaba
 * abierto, por ejemplo) — mismo criterio que `Aplicacion/ActualizarOrden`.
 *
 * `orden_id` y `nro_aplicacion` nunca viajan en `$parametrosCompartidos`: no
 * se editan (invariante de la tarea 127).
 */
final class ActualizarOrdenTrabajo
{
    private const array ATRIBUTOS_EDITABLES = [
        'humedad_min_pct',
        'viento_max_kmh',
        'temperatura_max_c',
        'humedad_max_pct',
        'altura_vuelo_m',
        'velocidad_vuelo_kmh',
        'ancho_pasada_m',
        'ph_agua',
        'ph_calda',
        'litros_ha',
        'kilos_ha',
        'calda_productos',
    ];

    public function __construct(private readonly ResolverCondicionPago $resolverCondicionPago) {}

    /**
     * @param  array<string, mixed>  $parametrosCompartidos  solo los datos editables de la cabecera; cualquier otra clave (orden_id, nro_aplicacion) se ignora.
     * @param  array<int, array{tarifa_id: int|null, negociado: bool, modalidad: string|null, monto_piloto: string|null, monto_auxiliar: string|null, motivo: string|null}>  $condicionesPorEquipo  equipo_trabajo_id => datos de pago; un equipo con algún trabajo no `abierto` se ignora en silencio (invariante 2: no se pisa la condición que ya va a alimentar un devengo).
     * @param  string|null  $motivo  por qué se corrige; obligatorio si algún trabajo ya está `cerrado`.
     *
     * @throws OrdenTrabajoNoEditable si TODOS los trabajos de la tanda ya están `validado`.
     * @throws MotivoRequerido si corrige una tanda con algún trabajo cerrado sin decir por qué.
     * @throws TarifaNoDisponible si la tarifa elegida para un equipo editable ya no está disponible.
     */
    public function ejecutar(OrdenTrabajo $ordenTrabajo, array $parametrosCompartidos, array $condicionesPorEquipo, ?string $motivo = null): OrdenTrabajo
    {
        return DB::transaction(function () use ($ordenTrabajo, $parametrosCompartidos, $condicionesPorEquipo, $motivo): OrdenTrabajo {
            /** @var OrdenTrabajo $actual */
            $actual = OrdenTrabajo::query()->lockForUpdate()->findOrFail($ordenTrabajo->id);
            $actual->load('trabajos.sesiones');

            $estadosTrabajos = $actual->trabajos
                ->map(fn (Trabajo $trabajo) => $trabajo->estadoTablero())
                ->all();

            if (! PoliticaEdicionOrdenTrabajo::admiteEdicion($estadosTrabajos)) {
                throw OrdenTrabajoNoEditable::porId((int) $actual->id);
            }

            $exigeMotivo = PoliticaEdicionOrdenTrabajo::exigeMotivo($estadosTrabajos);
            $motivo = trim((string) $motivo);

            if ($exigeMotivo && $motivo === '') {
                throw MotivoRequerido::paraCorregir();
            }

            $actual->fill(Arr::only($parametrosCompartidos, self::ATRIBUTOS_EDITABLES));

            if ($exigeMotivo) {
                $actual->fill(['motivo_correccion' => $motivo, 'corregida_at' => now()]);
            }

            $actual->save();

            $trabajosPorEquipo = $actual->trabajos->groupBy('equipo_trabajo_id');
            $condicionesActuales = OrdenTrabajoEquipo::query()->where('orden_trabajo_id', $actual->id)->get()->keyBy('equipo_trabajo_id');

            foreach ($condicionesPorEquipo as $equipoId => $pago) {
                $estadosEquipo = ($trabajosPorEquipo[$equipoId] ?? collect())
                    ->map(fn (Trabajo $trabajo) => $trabajo->estadoTablero())
                    ->all();

                /** @var OrdenTrabajoEquipo|null $condicionEquipo */
                $condicionEquipo = $condicionesActuales[$equipoId] ?? null;

                if ($condicionEquipo === null || ! PoliticaEdicionOrdenTrabajo::admiteCondicion($estadosEquipo)) {
                    continue;
                }

                $condicion = $this->resolverCondicionPago->ejecutar($pago);

                $condicionEquipo->fill([
                    'tarifa_id' => $condicion->tarifaId,
                    'modalidad_pago' => $condicion->modalidad,
                    'monto_piloto' => $condicion->montoPiloto,
                    'monto_auxiliar' => $condicion->montoAuxiliar,
                    'negociado' => $condicion->negociada,
                    'motivo_negociacion' => $condicion->negociada ? $pago['motivo'] : null,
                ]);
                $condicionEquipo->save();
            }

            return $actual->refresh();
        });
    }
}
