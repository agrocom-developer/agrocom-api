<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de cargas de combustible, filtrable por base, equipo
 * de trabajo, campaña y rango de fecha (HU-35, tarea 49; equipo/campaña
 * agregados por la tarea 73, HU-50) — "consultable por período" es el CA
 * literal de `plan_sprints.md` Sprint 10 (§220). Solo lectura, mismo patrón
 * de paginación que `ListarGastos`.
 *
 * Rango de fecha con `desde`/`hasta` independientes (a diferencia del
 * `periodo` de mes calendario de `ListarGastos`): el CA de esta HU pide
 * "por período" en general, no acotado a un mes — es, además, el corte del
 * COSTO INTERNO de Agrocom (ADR 0015 punto 6): la fecha, no la campaña.
 *
 * `resumen()` devuelve las cifras de la franja de KPI del listado (plan de
 * homogeneización §3.6, tarea 118) sobre la MISMA consulta filtrada que la
 * tabla: lo que se ve arriba cuenta exactamente las cargas que se ven abajo.
 */
final class ListarCombustibles
{
    /** @return LengthAwarePaginator<int, Combustible> */
    public function ejecutar(
        ?int $baseId = null,
        ?string $desde = null,
        ?string $hasta = null,
        ?int $equipoTrabajoId = null,
        ?int $campaniaId = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return $this->consulta($baseId, $desde, $hasta, $equipoTrabajoId, $campaniaId)
            ->orderByDesc('fecha')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Las cifras de la franja de KPI, con el filtro puesto:
     *
     * - `total`: suma de `monto` de todas las cargas que cumplen el filtro
     *   (antes solo se mostraba al elegir una cuadrilla, tarea 73 punto 5: el
     *   total por equipo es este mismo cálculo con el filtro de equipo).
     * - `cantidad`: cuántas cargas son.
     * - `litros`: suma de los litros cargados.
     * - `equipos`: cuántas cuadrillas distintas cargaron.
     *
     * Las sumas se hacen con `Brick\Math\BigDecimal` en PHP, NUNCA con `SUM()`
     * de SQL: en SQLite (motor de los tests) la agregación numérica pasa por
     * REAL/float, lo que violaría la invariante 6 de CLAUDE.md — mismo
     * criterio que `ListarGastos::resumen()`.
     *
     * @return array{total: string, cantidad: int, litros: string, equipos: int}
     */
    public function resumen(
        ?int $baseId = null,
        ?string $desde = null,
        ?string $hasta = null,
        ?int $equipoTrabajoId = null,
        ?int $campaniaId = null,
    ): array {
        $cargas = $this->consulta($baseId, $desde, $hasta, $equipoTrabajoId, $campaniaId)
            ->get(['monto', 'litros', 'equipo_trabajo_id']);

        $total = BigDecimal::of('0.00');
        $litros = BigDecimal::of('0.00');

        foreach ($cargas as $carga) {
            $total = $total->plus($carga->monto);
            $litros = $litros->plus($carga->litros);
        }

        return [
            'total' => (string) $total->toScale(2, RoundingMode::HalfUp),
            'cantidad' => $cargas->count(),
            'litros' => (string) $litros->toScale(2, RoundingMode::HalfUp),
            'equipos' => $cargas->pluck('equipo_trabajo_id')->unique()->count(),
        ];
    }

    /** @return Builder<Combustible> */
    private function consulta(
        ?int $baseId,
        ?string $desde,
        ?string $hasta,
        ?int $equipoTrabajoId,
        ?int $campaniaId,
    ): Builder {
        return Combustible::query()
            ->when($baseId !== null, fn ($consulta) => $consulta->where('base_id', $baseId))
            ->when($desde !== null, fn ($consulta) => $consulta->whereDate('fecha', '>=', $desde))
            ->when($hasta !== null, fn ($consulta) => $consulta->whereDate('fecha', '<=', $hasta))
            ->when($equipoTrabajoId !== null, fn ($consulta) => $consulta->where('equipo_trabajo_id', $equipoTrabajoId))
            ->when($campaniaId !== null, fn ($consulta) => $consulta->where('campania_id', $campaniaId));
    }
}
