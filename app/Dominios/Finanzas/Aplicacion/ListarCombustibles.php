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
     * Total de `monto` de las cargas que cumplen el filtro (tarea 73, punto
     * 5) — con `Brick\Math\BigDecimal` en PHP, NUNCA `SUM()` de SQL: mismo
     * criterio que `ListarGastos::total()`.
     */
    public function total(
        ?int $baseId = null,
        ?string $desde = null,
        ?string $hasta = null,
        ?int $equipoTrabajoId = null,
        ?int $campaniaId = null,
    ): string {
        $total = $this->consulta($baseId, $desde, $hasta, $equipoTrabajoId, $campaniaId)
            ->get(['monto'])
            ->reduce(
                fn (BigDecimal $acumulado, Combustible $combustible) => $acumulado->plus($combustible->monto),
                BigDecimal::of('0.00'),
            );

        return (string) $total->toScale(2, RoundingMode::HalfUp);
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
