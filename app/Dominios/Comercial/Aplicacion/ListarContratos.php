<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de contratos con búsqueda opcional por razón social
 * del cliente (HU-23, tarea 34). Solo lectura — mismo patrón de paginación
 * que `ListarClientes`. `cliente` viene precargada para que la vista pinte
 * la razón social sin una consulta N+1.
 */
final class ListarContratos
{
    /** @return LengthAwarePaginator<int, Contrato> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Contrato::query()
            ->with('cliente')
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => $consulta->whereHas(
                    'cliente',
                    fn ($sub) => $sub->where('razon_social', 'like', "%{$busqueda}%"),
                ),
            )
            ->orderByDesc('fecha_inicio')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
