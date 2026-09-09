<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de rendiciones de campo, filtrable por base y estado
 * (HU-34, tarea 48). Solo lectura, mismo patrón de paginación que
 * `ListarGastos`/`ListarAnticipos`.
 */
final class ListarRendiciones
{
    /** @return LengthAwarePaginator<int, Rendicion> */
    public function ejecutar(
        ?int $baseId = null,
        ?string $estado = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return Rendicion::query()
            ->when($baseId !== null, fn ($consulta) => $consulta->where('base_id', $baseId))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado))
            ->orderByDesc('fecha')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
