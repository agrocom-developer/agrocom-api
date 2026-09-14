<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\EquipoTrabajoNoVigente;
use App\Dominios\Operaciones\Dominio\Excepciones\HectareasAsignadasSuperanLote;
use App\Dominios\Operaciones\Dominio\Excepciones\LoteNoPerteneceAOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoVigenteParaAsignacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenLote;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reparto de una orden vigente entre equipos de trabajo (HU-70, tarea 85;
 * ampliada a N lotes por HU-92, tarea 107): el jefe de campo confirma desde
 * el panel, por cada equipo, qué lotes de la orden le tocan y con cuántas
 * hectáreas — cada PAR equipo↔lote nace como un `Trabajo` propio, abierto
 * ANTES de que el piloto toque el dispositivo (ver docblock de
 * `MaquinaEstadosTrabajo::abrirPorAsignacion()`).
 *
 * Cuatro guardas, en este orden — cada una evaluada ANTES de tocar la base:
 * una asignación que rechaza cualquiera de las cuatro no crea ningún
 * `Trabajo`, ni siquiera de los pares que sí eran válidos.
 *
 *   1. La orden existe (route model binding) y está `Vigente` — mismo
 *      criterio que `EscrituraSincronizacionEloquent::abrirTrabajo()`.
 *   2. Cada `equipo_trabajo_id` está vigente HOY
 *      ({@see LecturaEquipoTrabajo::vigentesAFecha()}) — nunca se lee
 *      `per_equipos_trabajo` directo (ADR 0003 regla 2).
 *   3. Cada `lote_id` que aparece en el reparto pertenece a `ope_orden_lotes`
 *      de ESTA orden — defensa en profundidad: `AsignarEquipoOrdenRequest`
 *      ya lo valida por forma.
 *   4. Por CADA lote (no por la orden completa: con N lotes, cada uno tiene
 *      su propio tope): la suma de hectáreas ya asignadas a ese lote
 *      (trabajos no eliminados de esta orden y ese lote — el soft delete ya
 *      excluye la fila del `sum()` por sí solo) más las nuevas de ESTE
 *      reparto (sumando lo que le toca a cada equipo del mismo lote, si dos
 *      equipos se reparten un mismo lote) no supera
 *      `ope_orden_lotes.hectareas_solicitadas` de ese lote — lo que la orden
 *      pidió de él, no `com_lotes.hectareas` completo (ADR 0003 regla 2:
 *      `Operaciones` lee su propia tabla directo, sin pasar por
 *      `Comercial`). Comparado con `Brick\Math\BigDecimal` (invariante 6 de
 *      CLAUDE.md), nunca `float`.
 */
final class AsignarEquiposOrden
{
    public function __construct(
        private readonly LecturaEquipoTrabajo $equipos,
        private readonly MaquinaEstadosTrabajo $maquinaTrabajo,
    ) {}

    /**
     * @param  list<array{equipo_trabajo_id: int, lotes: list<array{lote_id: int, hectareas: string}>}>  $asignaciones
     * @return list<Trabajo>
     *
     * @throws OrdenNoVigenteParaAsignacion
     * @throws EquipoTrabajoNoVigente
     * @throws LoteNoPerteneceAOrden
     * @throws HectareasAsignadasSuperanLote
     */
    public function ejecutar(OrdenAplicacion $orden, array $asignaciones): array
    {
        if ($orden->estado !== EstadoOrdenAplicacion::Vigente) {
            throw OrdenNoVigenteParaAsignacion::porOrden($orden->id);
        }

        $idsVigentes = array_map(
            static fn (DatosEquipoTrabajo $equipo): int => $equipo->id,
            $this->equipos->vigentesAFecha(now()->toDateString()),
        );

        foreach ($asignaciones as $asignacion) {
            if (! in_array($asignacion['equipo_trabajo_id'], $idsVigentes, true)) {
                throw EquipoTrabajoNoVigente::porId($asignacion['equipo_trabajo_id']);
            }
        }

        /** @var Collection<int, OrdenLote> $lotesOrden */
        $lotesOrden = $orden->ordenLotes()->get()->keyBy('lote_id');

        /** @var array<int, BigDecimal> $nuevoPorLote */
        $nuevoPorLote = [];

        foreach ($asignaciones as $asignacion) {
            foreach ($asignacion['lotes'] as $lote) {
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

        return DB::transaction(fn (): array => array_merge([], ...array_map(
            fn (array $asignacion): array => array_map(
                fn (array $lote): Trabajo => $this->maquinaTrabajo->abrirPorAsignacion([
                    'uuid_cliente' => (string) Str::uuid(),
                    'orden_id' => $orden->id,
                    'lote_id' => $lote['lote_id'],
                    'equipo_trabajo_id' => $asignacion['equipo_trabajo_id'],
                    'nro_aplicacion' => $orden->nro_aplicacion,
                    'hectareas_declaradas' => $lote['hectareas'],
                ]),
                $asignacion['lotes'],
            ),
            $asignaciones,
        )));
    }
}
