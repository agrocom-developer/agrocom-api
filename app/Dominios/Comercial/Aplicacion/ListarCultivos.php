<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de cultivos con búsqueda y filtros opcionales
 * (HU-48, tarea 71; filtros de tipo/ciclo/estado, ampliación 16/9/2026).
 * Solo lectura — mismo patrón de paginación que `ListarBases`.
 */
final class ListarCultivos
{
    /** @return LengthAwarePaginator<int, Cultivo> */
    public function ejecutar(
        ?string $busqueda = null,
        ?string $tipoCultivo = null,
        ?string $cicloVida = null,
        ?bool $activo = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return Cultivo::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => BusquedaTexto::aplicar($consulta, ['nombre_comun', 'nombre_cientifico'], $busqueda),
            )
            ->when($tipoCultivo !== null, fn ($consulta) => $consulta->where('tipo_cultivo', $tipoCultivo))
            ->when($cicloVida !== null, fn ($consulta) => $consulta->where('ciclo_vida', $cicloVida))
            ->when($activo !== null, fn ($consulta) => $consulta->where('activo', $activo))
            ->orderBy('nombre_comun')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
