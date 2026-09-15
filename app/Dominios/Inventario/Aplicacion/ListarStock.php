<?php

namespace App\Dominios\Inventario\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;
use Brick\Math\BigDecimal;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado del stock agregado por `(repuesto, base)`, con
 * búsqueda por código/descripción del repuesto y filtro por base (HU-36,
 * tarea 52), con la alerta de "por debajo del mínimo" calculada por fila —
 * nunca persistida (no hay tabla de alertas propia, mismo criterio que
 * `ListarBaterias`, tarea 51).
 *
 * El resultado se deja escrito como atributo NO persistido (`alerta`) sobre
 * cada `Stock` del paginador — así la vista no calcula ninguna regla de
 * negocio (checklist §8 de `docs/diseno/guia_pantalla_panel.md`), solo lee
 * un dato ya resuelto.
 */
final class ListarStock
{
    /** @return LengthAwarePaginator<int, Stock> */
    public function ejecutar(?string $busqueda = null, ?int $baseId = null, int $porPagina = 15): LengthAwarePaginator
    {
        $paginador = Stock::query()
            ->with('repuesto')
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => $consulta->whereHas(
                    'repuesto',
                    fn ($sub) => BusquedaTexto::aplicar($sub, ['codigo', 'descripcion'], $busqueda),
                ),
            )
            ->when($baseId !== null, fn ($consulta) => $consulta->where('base_id', $baseId))
            ->orderBy('base_id')
            ->paginate($porPagina)
            ->withQueryString();

        $paginador->getCollection()->each(function (Stock $stock): void {
            $stock->alerta = BigDecimal::of($stock->cantidad)->isLessThanOrEqualTo(BigDecimal::of($stock->stock_minimo));
        });

        return $paginador;
    }
}
