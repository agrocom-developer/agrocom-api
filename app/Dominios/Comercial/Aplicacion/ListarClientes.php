<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de clientes con búsqueda opcional (HU-22, tarea 33).
 * Solo lectura — mismo patrón de paginación que `ListarTrabajos`.
 */
final class ListarClientes
{
    /** @return LengthAwarePaginator<int, Cliente> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Cliente::query()
            ->withCount('contactos')
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => BusquedaTexto::aplicar($consulta, ['razon_social', 'nit'], $busqueda),
            )
            ->orderBy('razon_social')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
