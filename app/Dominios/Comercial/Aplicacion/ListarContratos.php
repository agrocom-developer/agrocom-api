<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de contratos con búsqueda opcional por razón social
 * del cliente y filtro opcional por campaña (ADR 0015 punto 1, tarea 69,
 * HU-23 tarea 34). Solo lectura — mismo patrón de paginación que
 * `ListarClientes`. `cliente` y `ventanas` vienen precargadas para que la
 * vista pinte la razón social y la columna "Ventanas" (HU-47, tarea 70,
 * "Día completo" cuando no hay ninguna) sin una consulta N+1.
 */
final class ListarContratos
{
    /** @return LengthAwarePaginator<int, Contrato> */
    public function ejecutar(?string $busqueda = null, ?int $campaniaId = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Contrato::query()
            ->with(['cliente', 'ventanas'])
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => $consulta->whereHas(
                    'cliente',
                    fn ($sub) => $sub->where('razon_social', 'like', "%{$busqueda}%"),
                ),
            )
            ->when(
                $campaniaId !== null,
                fn ($consulta) => $consulta->where('campania_id', $campaniaId),
            )
            ->orderByDesc('fecha_inicio')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
