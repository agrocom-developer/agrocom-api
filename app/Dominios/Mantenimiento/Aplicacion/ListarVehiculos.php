<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de vehículos con búsqueda y filtro por base/estado
 * (HU-40, tarea 50). Solo lectura — mismo patrón de paginación que
 * `ListarDrones`. Sin `with('base')`: `PerBase` es de otro módulo
 * (`Personal`) — el controlador resuelve las etiquetas por `DB::table`,
 * mismo criterio que `OrdenesController::etiquetasContrato`.
 */
final class ListarVehiculos
{
    /** @return LengthAwarePaginator<int, Vehiculo> */
    public function ejecutar(
        ?string $busqueda = null,
        ?int $baseId = null,
        ?string $estado = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return Vehiculo::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => BusquedaTexto::aplicar($consulta, ['identificador'], $busqueda),
            )
            ->when($baseId !== null, fn ($consulta) => $consulta->where('base_id', $baseId))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado))
            ->orderBy('identificador')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
