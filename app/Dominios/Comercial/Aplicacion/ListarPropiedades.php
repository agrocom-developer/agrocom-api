<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de propiedades con búsqueda opcional (ADR 0020).
 * Solo lectura.
 *
 * `withCount('lotes')` trae la cantidad de lotes de cada propiedad, y
 * `withSum('lotes as hectareas_totales', 'hectareas')` la suma de sus
 * hectáreas, ambas en la misma consulta (subconsulta agregada) — sin una
 * query N+1 nueva por fila. Las hectáreas totales de una propiedad son la
 * suma de sus lotes, nunca un dato que el usuario declare a mano (no hay
 * nada contra qué comparar el área dibujada en `geometria`, que es
 * puramente visual/de referencia).
 */
final class ListarPropiedades
{
    /** @return LengthAwarePaginator<int, Propiedad> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Propiedad::query()
            ->with('cliente')
            ->withCount('lotes')
            ->withSum('lotes as hectareas_totales', 'hectareas')
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn (Builder $consulta) => BusquedaTexto::aplicar($consulta, ['nombre'], $busqueda)
                    ->orWhereHas('cliente', fn (Builder $cliente) => BusquedaTexto::aplicar($cliente, ['razon_social'], $busqueda)),
            )
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
