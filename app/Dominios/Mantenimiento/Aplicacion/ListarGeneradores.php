<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de generadores con búsqueda y filtro por base/estado
 * (tarea 72, HU-49). Solo lectura — mismo patrón de paginación que
 * `ListarVehiculos`. Sin `with('base')`: `PerBase` es de otro módulo
 * (`Personal`) — el controlador resuelve las etiquetas por `DB::table`.
 */
final class ListarGeneradores
{
    /** @return LengthAwarePaginator<int, Generador> */
    public function ejecutar(
        ?string $busqueda = null,
        ?int $baseId = null,
        ?string $estado = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return Generador::query()
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
