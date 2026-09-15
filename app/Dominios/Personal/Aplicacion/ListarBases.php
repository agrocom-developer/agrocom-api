<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
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
                fn ($consulta) => BusquedaTexto::aplicar($consulta, ['nombre', 'ubicacion'], $busqueda),
            )
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
