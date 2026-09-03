<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de órdenes de mantenimiento con filtro por estado y
 * tipo de equipo (HU-37, tarea 53). Solo lectura, mismo patrón de paginación
 * que `ListarVehiculos`.
 */
final class ListarOrdenesMantenimiento
{
    /** @return LengthAwarePaginator<int, OrdenMantenimiento> */
    public function ejecutar(
        ?string $estado = null,
        ?string $equipoTipo = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return OrdenMantenimiento::query()
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado))
            ->when($equipoTipo !== null, fn ($consulta) => $consulta->where('equipo_tipo', $equipoTipo))
            ->orderByDesc('fecha_apertura')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
