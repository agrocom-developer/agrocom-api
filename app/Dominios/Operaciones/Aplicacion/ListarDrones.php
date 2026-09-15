<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de drones con búsqueda opcional (HU-27, tarea 36).
 * Solo lectura — mismo patrón de paginación que `ListarClientes`.
 */
final class ListarDrones
{
    /** @return LengthAwarePaginator<int, Dron> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Dron::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => BusquedaTexto::aplicar($consulta, ['identificador', 'modelo'], $busqueda),
            )
            ->orderBy('identificador')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
