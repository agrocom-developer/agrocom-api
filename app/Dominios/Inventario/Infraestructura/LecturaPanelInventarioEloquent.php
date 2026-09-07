<?php

namespace App\Dominios\Inventario\Infraestructura;

use App\Dominios\Inventario\Contratos\LecturaPanelInventario;
use App\Dominios\Inventario\Contratos\StockPanel;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;
use Brick\Math\BigDecimal;

/**
 * Implementación Eloquent del contrato de contenido de inventario del
 * dashboard (tarea 67).
 *
 * El filtro "bajo mínimo" se resuelve en SQL comparando columna con columna
 * (`cantidad <= stock_minimo`) y el ORDEN se calcula en PHP con `BigDecimal`:
 * ordenar por el faltante exige una resta, y una resta en SQL sobre SQLite
 * pasa por float. El filtro sí puede ir en SQL porque comparar dos DECIMAL
 * no los convierte.
 */
final class LecturaPanelInventarioEloquent implements LecturaPanelInventario
{
    public function stockBajoMinimo(int $limite): array
    {
        $filas = Stock::query()
            ->with('repuesto:id,codigo,descripcion')
            ->whereColumn('cantidad', '<=', 'stock_minimo')
            ->get();

        $conFaltante = $filas
            ->filter(fn (Stock $stock) => $stock->repuesto !== null)
            ->map(fn (Stock $stock) => [
                'faltante' => BigDecimal::of($stock->stock_minimo)->minus(BigDecimal::of($stock->cantidad)),
                'panel' => new StockPanel(
                    repuestoId: $stock->repuesto_id,
                    codigo: $stock->repuesto->codigo,
                    descripcion: $stock->repuesto->descripcion,
                    cantidad: (string) $stock->cantidad,
                    stockMinimo: (string) $stock->stock_minimo,
                    baseId: $stock->base_id,
                ),
            ])
            // Comparación con `BigDecimal::compareTo()`, no `sortByDesc` sobre
            // el string: ordenar "5.00" y "10.00" como texto pone el 5 primero.
            ->sort(fn (array $a, array $b) => $b['faltante']->compareTo($a['faltante']))
            ->take($limite);

        return $conFaltante->map(fn (array $fila) => $fila['panel'])->values()->all();
    }
}
