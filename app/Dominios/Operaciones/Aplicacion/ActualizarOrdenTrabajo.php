<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenTrabajoNoEditable;
use App\Dominios\Operaciones\Dominio\PoliticaEdicionOrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Edición de una Orden de Trabajo (tarea 127): la cabecera (indicaciones
 * compartidas — calda, límites climáticos, parámetros de vuelo). El reparto (equipos, lotes,
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

    /**
     * @param  array<string, mixed>  $parametrosCompartidos  solo los datos editables de la cabecera; cualquier otra clave (orden_id, nro_aplicacion) se ignora.
     * @param  string|null  $motivo  por qué se corrige; obligatorio si algún trabajo ya está `cerrado`.
     *
     * @throws OrdenTrabajoNoEditable si TODOS los trabajos de la tanda ya están `validado`.
     * @throws MotivoRequerido si corrige una tanda con algún trabajo cerrado sin decir por qué.
     */
    public function ejecutar(OrdenTrabajo $ordenTrabajo, array $parametrosCompartidos, ?string $motivo = null): OrdenTrabajo
    {
        return DB::transaction(function () use ($ordenTrabajo, $parametrosCompartidos, $motivo): OrdenTrabajo {
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

            return $actual->refresh();
        });
    }
}
