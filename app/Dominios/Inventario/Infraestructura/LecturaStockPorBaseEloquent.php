<?php

namespace App\Dominios\Inventario\Infraestructura;

use App\Dominios\Inventario\Contratos\DatosStockBase;
use App\Dominios\Inventario\Contratos\LecturaStockPorBase;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;

/**
 * Implementación Eloquent de {@see LecturaStockPorBase}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaContadoresPanelEloquent`: esa subcarpeta es solo para modelos.
 *
 * "Bajo el mínimo" es el mismo criterio que `ListarStock` y
 * `LecturaContadoresPanelEloquent::stockBajoMinimo()`: `cantidad <=
 * stock_minimo`, comparado por la base sobre DECIMAL (invariante 6).
 */
final class LecturaStockPorBaseEloquent implements LecturaStockPorBase
{
    public function deBase(int $baseId): DatosStockBase
    {
        return new DatosStockBase(
            repuestos: Stock::query()->where('base_id', $baseId)->count(),
            bajoMinimo: Stock::query()->where('base_id', $baseId)->whereColumn('cantidad', '<=', 'stock_minimo')->count(),
        );
    }
}
