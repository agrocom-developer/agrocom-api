<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de propiedades con búsqueda y filtros opcionales
 * (ADR 0020; filtros por cliente/departamento — adenda 16/9/2026 a ADR 0018
 * punto 1). Solo lectura.
 *
 * `withCount('lotes')` trae la cantidad de lotes de cada propiedad, y
 * `withSum('lotes as hectareas_totales', 'hectareas')` la suma de sus
 * hectáreas, ambas en la misma consulta (subconsulta agregada) — sin una
 * query N+1 nueva por fila. Las hectáreas totales de una propiedad son la
 * suma de sus lotes, nunca un dato que el usuario declare a mano (no hay
 * nada contra qué comparar el área dibujada en `geometria`, que es
 * puramente visual/de referencia).
 */
final class ListarPropiedades
{
    /** @return LengthAwarePaginator<int, Propiedad> */
    public function ejecutar(
        ?string $busqueda = null,
        ?int $clienteId = null,
        ?int $departamentoId = null,
        ?int $municipioId = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return Propiedad::query()
            ->with(['cliente', 'departamento', 'municipio'])
            ->withCount('lotes')
            ->withSum('lotes as hectareas_totales', 'hectareas')
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn (Builder $consulta) => BusquedaTexto::aplicar($consulta, ['nombre', 'localidad'], $busqueda)
                    ->orWhereHas('cliente', fn (Builder $cliente) => BusquedaTexto::aplicar($cliente, ['razon_social'], $busqueda)),
            )
            ->when($clienteId !== null, fn (Builder $consulta) => $consulta->where('cliente_id', $clienteId))
            ->when($departamentoId !== null, fn (Builder $consulta) => $consulta->where('departamento_id', $departamentoId))
            ->when($municipioId !== null, fn (Builder $consulta) => $consulta->where('municipio_id', $municipioId))
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
