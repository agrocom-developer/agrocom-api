<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de bases con búsqueda opcional (HU-26, tarea 37).
 * Solo lectura — mismo patrón de paginación que `ListarDrones`.
 */
final class ListarBases
{
    /** @return LengthAwarePaginator<int, PerBase> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return PerBase::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => $consulta->where(function ($sub) use ($busqueda): void {
                    $sub->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('ubicacion', 'like', "%{$busqueda}%");
                }),
            )
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
