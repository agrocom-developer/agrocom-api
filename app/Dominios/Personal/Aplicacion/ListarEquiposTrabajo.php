<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de equipos de trabajo con búsqueda y filtro por
 * estado/base (tarea 72, HU-49). Solo lectura — mismo patrón de paginación
 * que `ListarGeneradores`.
 */
final class ListarEquiposTrabajo
{
    /** @return LengthAwarePaginator<int, EquipoTrabajo> */
    public function ejecutar(
        ?string $busqueda = null,
        ?int $baseId = null,
        ?string $estado = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return EquipoTrabajo::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => $consulta->where(function ($sub) use ($busqueda) {
                    $sub->where('codigo', 'like', "%{$busqueda}%")
                        ->orWhere('nombre', 'like', "%{$busqueda}%");
                }),
            )
            ->when($baseId !== null, fn ($consulta) => $consulta->where('base_id', $baseId))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado))
            ->orderBy('codigo')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
