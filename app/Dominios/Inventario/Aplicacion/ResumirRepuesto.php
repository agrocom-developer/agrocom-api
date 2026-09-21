<?php

namespace App\Dominios\Inventario\Aplicacion;

use App\Dominios\Inventario\Dominio\NivelDeStock;
use App\Dominios\Inventario\Dominio\TipoMovimientoInventario;
use App\Dominios\Inventario\Infraestructura\Eloquent\MovimientoStock;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;
use Brick\Math\BigDecimal;

/**
 * Caso de uso de LECTURA: lo que Inventario sabe de UN repuesto, para el
 * resumen relacionado de su ficha de edición (tarea 117, §6.3.1 de la guía de
 * pantalla). Tres preguntas, tres métodos, porque cada tarjeta del resumen se
 * gatea por un permiso distinto y no tiene sentido consultar lo que el rol
 * activo no va a ver.
 *
 * Solo lee tablas de este módulo (`inv_stock`, `inv_movimientos`). Lo de otros
 * módulos —las órdenes de mantenimiento que consumieron el repuesto— llega
 * aparte por el `Contratos/` de su dueño: acá solo se entregan los ids que
 * este módulo escribió como `orden_mantenimiento_id`.
 *
 * Las cantidades son DECIMAL como texto y se suman con `BigDecimal`, nunca con
 * `float` (invariante 6): el total siempre se puede recalcular desde las filas.
 */
final class ResumirRepuesto
{
    /**
     * Lo que hay en las bases: la existencia total, en cuántas bases queda
     * algo y cuántas filas piden reposición (misma regla que la alerta del
     * listado de stock, {@see NivelDeStock}).
     *
     * `filas` distingue «nunca tuvo stock» de «tuvo y se agotó».
     *
     * @return array{filas: int, total: string, basesConStock: int, bajoMinimo: int}
     */
    public function existencias(Repuesto $repuesto): array
    {
        $total = BigDecimal::zero();
        $basesConStock = 0;
        $bajoMinimo = 0;

        $filas = Stock::query()
            ->where('repuesto_id', $repuesto->id)
            ->get(['cantidad', 'stock_minimo']);

        foreach ($filas as $fila) {
            $total = $total->plus(BigDecimal::of($fila->cantidad));

            if (! NivelDeStock::sinExistencias($fila->cantidad)) {
                $basesConStock++;
            }

            if (NivelDeStock::bajoMinimo($fila->cantidad, $fila->stock_minimo)) {
                $bajoMinimo++;
            }
        }

        return [
            'filas' => $filas->count(),
            'total' => (string) $total,
            'basesConStock' => $basesConStock,
            'bajoMinimo' => $bajoMinimo,
        ];
    }

    /**
     * Cuántos asientos tiene y los últimos, del más reciente al más viejo. El
     * instante viaja como ISO 8601 (la base guarda UTC) para que quien lo pinta
     * lo pase a la zona del usuario y elija el formato.
     *
     * @return array{total: int, ultimos: list<array{tipo: string, sentido: string|null, cantidad: string, instante: string|null}>}
     */
    public function movimientos(Repuesto $repuesto, int $limite = 3): array
    {
        $consulta = MovimientoStock::query()->where('repuesto_id', $repuesto->id);

        $ultimos = (clone $consulta)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limite)
            ->get(['tipo', 'sentido', 'cantidad', 'created_at'])
            ->map(fn (MovimientoStock $movimiento): array => [
                'tipo' => $movimiento->tipo,
                'sentido' => $movimiento->sentido,
                'cantidad' => $movimiento->cantidad,
                'instante' => $movimiento->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return ['total' => $consulta->count(), 'ultimos' => $ultimos];
    }

    /**
     * Las órdenes de mantenimiento que descontaron este repuesto y cuántas
     * unidades sumaron entre todas. Una orden que consumió el repuesto en dos
     * bases cuenta una sola vez.
     *
     * Solo las SALIDAS con orden: es lo que escribe `EscrituraConsumoStock` al
     * cerrarla. Un ajuste o un traslado con motivo no es un consumo.
     *
     * @return array{ordenIds: list<int>, unidades: string}
     */
    public function consumoEnOrdenes(Repuesto $repuesto): array
    {
        $salidas = MovimientoStock::query()
            ->where('repuesto_id', $repuesto->id)
            ->where('tipo', TipoMovimientoInventario::Salida->value)
            ->whereNotNull('orden_mantenimiento_id')
            ->get(['orden_mantenimiento_id', 'cantidad']);

        $unidades = BigDecimal::zero();

        foreach ($salidas as $salida) {
            $unidades = $unidades->plus(BigDecimal::of($salida->cantidad));
        }

        /** @var list<int> $ordenIds */
        $ordenIds = $salidas
            ->pluck('orden_mantenimiento_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return ['ordenIds' => $ordenIds, 'unidades' => (string) $unidades];
    }
}
