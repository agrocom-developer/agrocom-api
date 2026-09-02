<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de campos con búsqueda opcional (HU-24, tarea 35).
 * Solo lectura — mismo patrón de paginación que `ListarClientes`.
 *
 * `withSum('lotes as hectareas_totales', 'hectareas')` trae la suma de
 * hectáreas de los lotes de cada campo en la misma consulta (subconsulta
 * agregada) — el listado no dispara una query nueva por fila para calcular
 * el total (prompt de la tarea: "si el dato ya está cargado, no una query
 * N+1 nueva por fila").
 */
final class ListarCampos
{
    /** @return LengthAwarePaginator<int, Campo> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Campo::query()
            ->with('cliente')
            ->withCount('lotes')
            ->withSum('lotes as hectareas_totales', 'hectareas')
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn (Builder $consulta) => $consulta->where(function (Builder $sub) use ($busqueda): void {
                    $sub->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhereHas('cliente', fn (Builder $cliente) => $cliente->where('razon_social', 'like', "%{$busqueda}%"));
                }),
            )
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
