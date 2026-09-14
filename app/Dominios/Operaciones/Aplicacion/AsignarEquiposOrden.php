<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\EquipoTrabajoNoVigente;
use App\Dominios\Operaciones\Dominio\Excepciones\HectareasAsignadasSuperanLote;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoVigenteParaAsignacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reparto de una orden vigente entre equipos de trabajo (HU-70, tarea 85):
 * "dónde asignarle el trabajo al piloto" — el jefe de campo confirma desde el
 * panel qué equipo cubre cuántas hectáreas del lote de la orden, y cada
 * reparto nace como un `Trabajo` propio, abierto ANTES de que el piloto toque
 * el dispositivo (ver docblock de `MaquinaEstadosTrabajo::abrirPorAsignacion()`).
 *
 * Tres guardas, en este orden — el mismo orden en que las pide el prompt de
 * la tarea, cada una evaluada ANTES de tocar la base: una asignación que
 * rechaza cualquiera de las tres no crea ningún `Trabajo`, ni siquiera de los
 * equipos que sí eran válidos.
 *
 *   1. La orden existe (resuelta por el llamador vía route model binding —
 *      Laravel ya responde 404 si el id no existe) y está `Vigente` — mismo
 *      criterio que `EscrituraSincronizacionEloquent::abrirTrabajo()`
 *      (tarea 12): sin orden vigente no hay trabajo legítimo que abrir.
 *   2. Cada `equipo_trabajo_id` está vigente HOY
 *      ({@see LecturaEquipoTrabajo::vigentesAFecha()}) — nunca se lee
 *      `per_equipos_trabajo` directo (ADR 0003 regla 2).
 *   3. La suma de hectáreas ya asignadas a esta orden (trabajos no
 *      eliminados — el soft delete ya excluye la fila del `sum()` por sí
 *      solo) más las nuevas no supera `com_lotes.hectareas` del lote de la
 *      orden, leído por {@see LecturaLotes::obtenerPorId()} — nunca un
 *      `DB::table` directo a `Comercial` (ADR 0003 regla 2). Comparado con
 *      `Brick\Math\BigDecimal` (invariante 6 de CLAUDE.md), nunca `float`.
 *
 * `orden_id`/`lote_id`/`nro_aplicacion` de cada `Trabajo` nuevo son siempre
 * los de la orden — HU-70 reparte hectáreas, nunca lotes (ver "Qué NO hacer"
 * del prompt de la tarea).
 */
final class AsignarEquiposOrden
{
    public function __construct(
        private readonly LecturaEquipoTrabajo $equipos,
        private readonly LecturaLotes $lotes,
        private readonly MaquinaEstadosTrabajo $maquinaTrabajo,
    ) {}

    /**
     * @param  list<array{equipo_trabajo_id: int, hectareas: string}>  $asignaciones
     * @return list<Trabajo>
     *
     * @throws OrdenNoVigenteParaAsignacion
     * @throws EquipoTrabajoNoVigente
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

        // `->hectareas ?? '0'` (sin `?->`: `??` ya cubre el acceso a una
        // propiedad de un objeto nulo, PHPStan lo marca redundante): un lote
        // referenciado por una orden vigente no puede estar borrado
        // (`VerificadorHistorialLote` lo impide mientras tenga órdenes o
        // trabajos asociados) — este `null` es defensivo por el tipo
        // nullable del contrato, no un camino que se espere alcanzar; si
        // igual se alcanzara, "cero hectáreas disponibles" es la falla
        // segura (rechaza en vez de asignar a ciegas).
        $hectareasLote = BigDecimal::of($this->lotes->obtenerPorId($orden->lote_id)->hectareas ?? '0');

        $totalYaAsignado = BigDecimal::of((string) Trabajo::query()->where('orden_id', $orden->id)->sum('hectareas_declaradas'));
        $totalNuevo = array_reduce(
            $asignaciones,
            static fn (BigDecimal $acumulado, array $asignacion): BigDecimal => $acumulado->plus($asignacion['hectareas']),
            BigDecimal::zero(),
        );
        $totalFinal = $totalYaAsignado->plus($totalNuevo);

        if ($totalFinal->isGreaterThan($hectareasLote)) {
            throw HectareasAsignadasSuperanLote::porOrden($orden->id, (string) $totalFinal, (string) $hectareasLote);
        }

        return DB::transaction(fn (): array => array_map(
            fn (array $asignacion): Trabajo => $this->maquinaTrabajo->abrirPorAsignacion([
                'uuid_cliente' => (string) Str::uuid(),
                'orden_id' => $orden->id,
                'lote_id' => $orden->lote_id,
                'equipo_trabajo_id' => $asignacion['equipo_trabajo_id'],
                'nro_aplicacion' => $orden->nro_aplicacion,
                'hectareas_declaradas' => $asignacion['hectareas'],
            ]),
            $asignaciones,
        ));
    }
}
