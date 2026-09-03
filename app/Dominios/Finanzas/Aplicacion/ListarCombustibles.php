<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de cargas de combustible, filtrable por base y por
 * rango de fecha (HU-35, tarea 49) — "consultable por período" es el CA
 * literal de `plan_sprints.md` Sprint 10 (§220). Solo lectura, mismo patrón
 * de paginación que `ListarGastos`.
 *
 * Rango de fecha con `desde`/`hasta` independientes (a diferencia del
 * `periodo` de mes calendario de `ListarGastos`): el CA de esta HU pide
 * "por período" en general, no acotado a un mes.
 */
final class ListarCombustibles
{
    /** @return LengthAwarePaginator<int, Combustible> */
    public function ejecutar(
        ?int $baseId = null,
        ?string $desde = null,
        ?string $hasta = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return Combustible::query()
            ->when($baseId !== null, fn ($consulta) => $consulta->where('base_id', $baseId))
            ->when($desde !== null, fn ($consulta) => $consulta->whereDate('fecha', '>=', $desde))
            ->when($hasta !== null, fn ($consulta) => $consulta->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
