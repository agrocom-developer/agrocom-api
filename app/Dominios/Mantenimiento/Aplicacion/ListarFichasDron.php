<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\FichaDron;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Listado de fichas de inventario de dron con búsqueda por identificador
 * (HU-82, tarea 97). Sin alerta ni cruce con otro módulo — a diferencia de
 * `ListarBaterias`, esta ficha no calcula ninguna regla de negocio, solo
 * lista lo persistido.
 */
final class ListarFichasDron
{
    /** @return LengthAwarePaginator<int, FichaDron> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return FichaDron::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => $consulta->where('identificador_dron', 'like', "%{$busqueda}%"),
            )
            ->orderBy('identificador_dron')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
