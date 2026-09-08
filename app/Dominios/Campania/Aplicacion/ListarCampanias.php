<?php

namespace App\Dominios\Campania\Aplicacion;

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de campañas con búsqueda opcional por código o nombre
 * (ADR 0015 punto 1, tarea 69). Solo lectura — mismo patrón de paginación que
 * `ListarBases`. Orden por `fecha_inicio` descendente: la campaña más
 * reciente encabeza el listado, mismo criterio de "lo último primero" que
 * `ListarGastos` con `fecha`.
 */
final class ListarCampanias
{
    /** @return LengthAwarePaginator<int, Campania> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Campania::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => $consulta->where(function ($sub) use ($busqueda): void {
                    $sub->where('codigo', 'like', "%{$busqueda}%")
                        ->orWhere('nombre', 'like', "%{$busqueda}%");
                }),
            )
            ->orderByDesc('fecha_inicio')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
