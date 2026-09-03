<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de personas con búsqueda opcional (HU-26, tarea 37).
 * Solo lectura — mismo patrón de paginación que `ListarBases`. Carga `base`
 * para que el listado muestre el nombre de la base sin N+1 en la vista.
 */
final class ListarPersonas
{
    /** @return LengthAwarePaginator<int, PerPersona> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return PerPersona::query()
            ->with('base')
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => $consulta->where('nombre', 'like', "%{$busqueda}%"),
            )
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
