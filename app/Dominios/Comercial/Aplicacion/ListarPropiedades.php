<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de propiedades con búsqueda opcional (ADR 0018).
 * Solo lectura — mismo patrón de paginación que `ListarCampos`.
 *
 * `withCount('campos')` trae la cantidad de campos de cada propiedad en la
 * misma consulta (subconsulta agregada), sin una query N+1 nueva por fila.
 */
final class ListarPropiedades
{
    /** @return LengthAwarePaginator<int, Propiedad> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Propiedad::query()
            ->with('cliente')
            ->withCount('campos')
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
