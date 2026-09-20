<?php

namespace App\Dominios\Inventario\Infraestructura;

use App\Dominios\Inventario\Contratos\DatosConsumoOrden;
use App\Dominios\Inventario\Contratos\DatosConsumosOrden;
use App\Dominios\Inventario\Contratos\LecturaConsumosPorOrden;
use App\Dominios\Inventario\Dominio\TipoMovimientoInventario;
use App\Dominios\Inventario\Infraestructura\Eloquent\MovimientoStock;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

/**
 * Implementación Eloquent de {@see LecturaConsumosPorOrden}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaStockPorBaseEloquent`: esa subcarpeta es solo para modelos.
 *
 * Lee las salidas de `inv_movimientos` que llevan el id de la orden —las que
 * escribió `EscrituraConsumoStockEloquent` al cerrarla— y les pega el código
 * y la descripción del repuesto. El costo de cada línea se recalcula siempre
 * (`cantidad × costo_unitario`) con `BigDecimal`, nunca con float
 * (invariante 6 de CLAUDE.md) ni leído de una columna cacheada, que no
 * existe.
 */
final class LecturaConsumosPorOrdenEloquent implements LecturaConsumosPorOrden
{
    public function deOrden(int $ordenMantenimientoId): array
    {
        return $this->lineas($ordenMantenimientoId)->all();
    }

    public function resumenDeOrden(int $ordenMantenimientoId): DatosConsumosOrden
    {
        $lineas = $this->lineas($ordenMantenimientoId);
        $unidades = BigDecimal::zero();
        $costoTotal = BigDecimal::zero();

        foreach ($lineas as $linea) {
            $unidades = $unidades->plus(BigDecimal::of($linea->cantidad));
            $costoTotal = $costoTotal->plus(BigDecimal::of($linea->costoTotal));
        }

        return new DatosConsumosOrden(
            lineas: $lineas->count(),
            unidades: (string) $unidades,
            costoTotal: (string) $costoTotal,
        );
    }

    /** @return Collection<int, DatosConsumoOrden> */
    private function lineas(int $ordenMantenimientoId): Collection
    {
        return MovimientoStock::query()
            ->join('inv_repuestos', 'inv_repuestos.id', '=', 'inv_movimientos.repuesto_id')
            ->where('inv_movimientos.orden_mantenimiento_id', $ordenMantenimientoId)
            ->where('inv_movimientos.tipo', TipoMovimientoInventario::Salida->value)
            ->orderBy('inv_movimientos.id')
            ->get([
                'inv_movimientos.repuesto_id',
                'inv_movimientos.base_id',
                'inv_movimientos.cantidad',
                'inv_movimientos.costo_unitario',
                'inv_repuestos.codigo',
                'inv_repuestos.descripcion',
            ])
            ->map(function (MovimientoStock $movimiento): DatosConsumoOrden {
                $cantidad = BigDecimal::of((string) $movimiento->cantidad);
                $costoUnitario = BigDecimal::of((string) ($movimiento->costo_unitario ?? '0'));

                return new DatosConsumoOrden(
                    repuestoId: $movimiento->repuesto_id,
                    codigo: (string) $movimiento->getAttribute('codigo'),
                    descripcion: (string) $movimiento->getAttribute('descripcion'),
                    baseId: $movimiento->base_id,
                    cantidad: (string) $cantidad,
                    costoUnitario: (string) $costoUnitario,
                    costoTotal: (string) $cantidad->multipliedBy($costoUnitario),
                );
            })
            ->values();
    }
}
