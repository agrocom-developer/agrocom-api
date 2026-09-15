<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de cultivos con búsqueda opcional (HU-48, tarea 71).
 * Solo lectura — mismo patrón de paginación que `ListarBases`.
 */
final class ListarCultivos
{
    /** @return LengthAwarePaginator<int, Cultivo> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Cultivo::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => BusquedaTexto::aplicar($consulta, ['nombre'], $busqueda),
            )
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
