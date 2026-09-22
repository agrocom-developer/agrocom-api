<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Mezclas\Contratos\EscrituraMezclas;
use App\Dominios\Mezclas\Contratos\RegistroMezcla;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\CaldaNoRegistrada;
use App\Dominios\Operaciones\Dominio\Excepciones\EquipoTrabajoNoVigente;
use App\Dominios\Operaciones\Dominio\Excepciones\HectareasAsignadasSuperanLote;
use App\Dominios\Operaciones\Dominio\Excepciones\LoteNoPerteneceAOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoVigenteParaAsignacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenLote;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alta de una Orden de Trabajo — una TANDA de una orden vigente (reforma
 * 18/9/2026): el encargado confirma, en un solo submit, los parámetros
 * COMPARTIDOS de esa tanda (límites climáticos, parámetros de vuelo, Ph si
 * la orden es líquida, calda) y qué equipos participan, con qué lotes,
 * hectáreas y turno le toca a cada uno — cada par equipo↔lote nace como un
 * `Trabajo` propio, colgado de la `OrdenTrabajo` recién creada.
 *
 * Antes de esta reforma, esta clase creaba directamente los `Trabajo` con
 * clima/vuelo repetidos por fila y sin cabecera (HU-70, tarea 85; HU-92,
 * tarea 107). Ahora SIEMPRE crea una `OrdenTrabajo` nueva primero —nunca
 * reusa una existente: cada llamada es una tanda distinta, aunque sea sobre
 * la misma orden (ej.: semana 1 con 2 equipos, y días después otra tanda
 * con 1 equipo para lo que falta)—, y los `Trabajo` cuelgan de ella.
 *
 * Cuatro guardas, en este orden, evaluadas ANTES de tocar la base — una
 * asignación que rechaza cualquiera no crea nada, ni la cabecera:
 *
 *   1. La orden existe (route model binding) y está `Vigente`.
 *   2. Cada `equipo_trabajo_id` está vigente HOY
 *      ({@see LecturaEquipoTrabajo::vigentesAFecha()}).
 *   3. Cada `lote_id` que aparece en el reparto pertenece a `ope_orden_lotes`
 *      de ESTA orden.
 *   4. Por CADA lote: la suma de hectáreas ya asignadas (trabajos no
 *      eliminados de esta orden y ese lote) más las nuevas de ESTA tanda no
 *      supera `ope_orden_lotes.hectareas_solicitadas` de ese lote. Comparado
 *      con `Brick\Math\BigDecimal` (invariante 6 de CLAUDE.md).
 *
 * La calda tiene dos formas. La pantalla "Orden de Trabajo" la manda como
 * casillas sin cantidades (`calda_productos`, valores de
 * `Dominio\ProductoCalda`) y queda en la cabecera de la tanda. La pantalla
 * vieja de reparto todavía la manda como lista de productos con cantidad y
 * unidad (`calda`), y esa —si se cargó— se registra vía
 * `Mezclas\Contratos\EscrituraMezclas`
 * — cruce de módulo por contrato (ADR 0003 regla 2, mismo criterio que
 * `LecturaEquipoTrabajo` de Personal) — una vez POR CADA `Trabajo` creado,
 * con los mismos productos replicados: el esquema de `Mezclas` sigue atado a
 * `trabajo_id` (no se toca), así que la cabecera compartida se traduce en
 * una fila de `Mezcla` por cada equipo×lote, igual criterio que ya usa esta
 * clase para replicar clima/vuelo antes de esta reforma.
 */
final class CrearOrdenTrabajo
{
    public function __construct(
        private readonly LecturaEquipoTrabajo $equipos,
        private readonly MaquinaEstadosTrabajo $maquinaTrabajo,
        private readonly EscrituraMezclas $mezclas,
    ) {}

    /**
     * @param  array{humedad_min_pct: string|null, viento_max_kmh: string|null, temperatura_max_c: string|null, humedad_max_pct: string|null, altura_vuelo_m: string|null, velocidad_vuelo_kmh: string|null, ancho_pasada_m: string|null, ph_agua: string|null, ph_calda: string|null, litros_ha?: string|null, kilos_ha?: string|null, calda_productos?: list<string>, calda: list<array{producto: string, cantidad: string, unidad: string}>}  $parametrosCompartidos  de TODA la tanda
     * @param  list<array{equipo_trabajo_id: int, lotes: list<array{lote_id: int, hectareas: string, turno: string, turno_hora_inicio: string|null, turno_hora_fin: string|null}>}>  $equipos
     *
     * @throws OrdenNoVigenteParaAsignacion
     * @throws EquipoTrabajoNoVigente
     * @throws LoteNoPerteneceAOrden
     * @throws HectareasAsignadasSuperanLote
     * @throws CaldaNoRegistrada
     */
    public function ejecutar(OrdenAplicacion $orden, array $parametrosCompartidos, array $equipos): OrdenTrabajo
    {
        if ($orden->estado !== EstadoOrdenAplicacion::Vigente) {
            throw OrdenNoVigenteParaAsignacion::porOrden($orden->id);
        }

        $idsVigentes = array_map(
            static fn (DatosEquipoTrabajo $equipo): int => $equipo->id,
            $this->equipos->vigentesAFecha(now()->toDateString()),
        );

        foreach ($equipos as $equipo) {
            if (! in_array($equipo['equipo_trabajo_id'], $idsVigentes, true)) {
                throw EquipoTrabajoNoVigente::porId($equipo['equipo_trabajo_id']);
            }
        }

        /** @var Collection<int, OrdenLote> $lotesOrden */
        $lotesOrden = $orden->ordenLotes()->get()->keyBy('lote_id');

        /** @var array<int, BigDecimal> $nuevoPorLote */
        $nuevoPorLote = [];

        foreach ($equipos as $equipo) {
            foreach ($equipo['lotes'] as $lote) {
                $loteId = $lote['lote_id'];

                if (! $lotesOrden->has($loteId)) {
                    throw LoteNoPerteneceAOrden::porLote($loteId, $orden->id);
                }

                $nuevoPorLote[$loteId] = ($nuevoPorLote[$loteId] ?? BigDecimal::zero())->plus($lote['hectareas']);
            }
        }

        foreach ($nuevoPorLote as $loteId => $nuevo) {
            $yaAsignado = BigDecimal::of((string) Trabajo::query()
                ->where('orden_id', $orden->id)
                ->where('lote_id', $loteId)
                ->sum('hectareas_declaradas'));

            $totalFinal = $yaAsignado->plus($nuevo);
            $hectareasSolicitadas = BigDecimal::of((string) $lotesOrden[$loteId]->hectareas_solicitadas);

            if ($totalFinal->isGreaterThan($hectareasSolicitadas)) {
                throw HectareasAsignadasSuperanLote::porOrdenYLote($orden->id, $loteId, (string) $totalFinal, (string) $hectareasSolicitadas);
            }
        }

        return DB::transaction(function () use ($orden, $parametrosCompartidos, $equipos): OrdenTrabajo {
            $ordenTrabajo = OrdenTrabajo::create([
                'orden_id' => $orden->id,
                'nro_aplicacion' => $orden->nro_aplicacion,
                'humedad_min_pct' => $parametrosCompartidos['humedad_min_pct'] ?? null,
                'viento_max_kmh' => $parametrosCompartidos['viento_max_kmh'] ?? null,
                'temperatura_max_c' => $parametrosCompartidos['temperatura_max_c'] ?? null,
                'humedad_max_pct' => $parametrosCompartidos['humedad_max_pct'] ?? null,
                'altura_vuelo_m' => $parametrosCompartidos['altura_vuelo_m'] ?? null,
                'velocidad_vuelo_kmh' => $parametrosCompartidos['velocidad_vuelo_kmh'] ?? null,
                'ancho_pasada_m' => $parametrosCompartidos['ancho_pasada_m'] ?? null,
                'ph_agua' => $parametrosCompartidos['ph_agua'] ?? null,
                'ph_calda' => $parametrosCompartidos['ph_calda'] ?? null,
                'litros_ha' => $parametrosCompartidos['litros_ha'] ?? null,
                'kilos_ha' => $parametrosCompartidos['kilos_ha'] ?? null,
                'calda_productos' => ($parametrosCompartidos['calda_productos'] ?? []) === [] ? null : $parametrosCompartidos['calda_productos'],
            ]);

            foreach ($equipos as $equipo) {
                foreach ($equipo['lotes'] as $lote) {
                    $trabajo = $this->maquinaTrabajo->abrirPorAsignacion([
                        'uuid_cliente' => (string) Str::uuid(),
                        'orden_id' => $orden->id,
                        'lote_id' => $lote['lote_id'],
                        'orden_trabajo_id' => $ordenTrabajo->id,
                        'equipo_trabajo_id' => $equipo['equipo_trabajo_id'],
                        'nro_aplicacion' => $orden->nro_aplicacion,
                        'hectareas_declaradas' => $lote['hectareas'],
                        'turno' => $lote['turno'],
                        'turno_hora_inicio' => $lote['turno_hora_inicio'],
                        'turno_hora_fin' => $lote['turno_hora_fin'],
                    ]);

                    $this->registrarCalda($orden, $trabajo, $parametrosCompartidos['calda']);
                }
            }

            return $ordenTrabajo->refresh()->load('trabajos');
        });
    }

    /**
     * @param  list<array{producto: string, cantidad: string, unidad: string}>  $productos
     *
     * @throws CaldaNoRegistrada
     */
    private function registrarCalda(OrdenAplicacion $orden, Trabajo $trabajo, array $productos): void
    {
        if ($productos === []) {
            return;
        }

        $registro = RegistroMezcla::intentarDesdeArreglo([
            'uuid_cliente' => (string) Str::uuid(),
            'trabajo_uuid_cliente' => $trabajo->uuid_cliente,
            'hora' => now()->toIso8601String(),
            'productos' => $productos,
        ]);

        $resultado = $registro !== null ? $this->mezclas->registrarMezcla($registro) : null;

        if ($registro === null || $resultado->estado !== 'aplicado') {
            throw CaldaNoRegistrada::porOrdenId($orden->id);
        }
    }
}
