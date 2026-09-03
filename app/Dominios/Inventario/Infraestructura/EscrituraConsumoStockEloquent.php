<?php

namespace App\Dominios\Inventario\Infraestructura;

use App\Dominios\Inventario\Aplicacion\RegistrarMovimientoStock;
use App\Dominios\Inventario\Contratos\EscrituraConsumoStock;
use App\Dominios\Inventario\Dominio\TipoMovimientoInventario;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use Brick\Math\BigDecimal;

/**
 * Implementación de {@see EscrituraConsumoStock} sobre
 * `RegistrarMovimientoStock` (HU-37, tarea 53): reusa la guarda de "el
 * stock nunca queda negativo" y el `lockForUpdate()` que esa clase ya
 * tiene — no duplica nada de esa lógica acá.
 *
 * `consumir()` no abre transacción propia: `RegistrarMovimientoStock::ejecutar()`
 * ya abre la suya (`DB::transaction()`), que Postgres anida como
 * `SAVEPOINT` dentro de la transacción exterior que abrió
 * `MaquinaEstadosOrdenMantenimiento::cerrar()` — si una línea posterior
 * falla, el `DB::transaction()` exterior revierte todo, incluida esta
 * salida.
 */
final class EscrituraConsumoStockEloquent implements EscrituraConsumoStock
{
    public function __construct(private readonly RegistrarMovimientoStock $registrarMovimientoStock) {}

    public function consumir(int $repuestoId, int $baseId, string $cantidad, ?int $ordenMantenimientoId): string
    {
        $repuesto = Repuesto::query()->findOrFail($repuestoId);

        $movimiento = $this->registrarMovimientoStock->ejecutar(
            tipo: TipoMovimientoInventario::Salida,
            repuesto: $repuesto,
            baseId: $baseId,
            cantidad: $cantidad,
            ordenMantenimientoId: $ordenMantenimientoId,
        );

        $costoUnitario = $movimiento->costo_unitario ?? '0';

        return (string) BigDecimal::of($costoUnitario)->multipliedBy($cantidad);
    }
}
