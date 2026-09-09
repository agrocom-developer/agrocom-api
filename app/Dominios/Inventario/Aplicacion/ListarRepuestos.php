<?php

namespace App\Dominios\Inventario\Aplicacion;

use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado del catálogo de repuestos con búsqueda por código o
 * descripción (HU-36, tarea 52). SIN alerta por fila, a diferencia de
 * `ListarStock`: la cantidad y el punto de reposición viven en `inv_stock`,
 * por `(repuesto, base)` — un repuesto del catálogo no tiene, por sí solo,
 * "cuánto stock" ni "cuál mínimo" (puede tener varias filas de stock, una
 * por base, cada una con su propia alerta). La alerta se calcula donde
 * corresponde: en `ListarStock`.
 */
final class ListarRepuestos
{
    /** @return LengthAwarePaginator<int, Repuesto> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Repuesto::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => $consulta->where(fn ($sub) => $sub
                    ->where('codigo', 'like', "%{$busqueda}%")
                    ->orWhere('descripcion', 'like', "%{$busqueda}%")),
            )
            ->orderBy('codigo')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
