<?php

namespace App\Dominios\Inventario\Contratos;

use App\Dominios\Inventario\Dominio\Excepciones\StockInsuficiente;

/**
 * Contrato de escritura de `Inventario` para el cierre de una orden de
 * mantenimiento (HU-37, tarea 53; ADR 0003 regla 2). Un módulo externo
 * (`Mantenimiento`) nunca escribe `inv_stock`/`inv_movimientos`
 * directamente ni conoce `RegistrarMovimientoStock` — solo este método.
 *
 * `consumir()` registra una `salida` (mismo mecanismo de guarda que
 * cualquier otra salida: `lockForUpdate()` + `StockInsuficiente` antes de
 * dejar el stock negativo) dentro de la transacción que ya abrió el
 * llamador — esta implementación NO abre `DB::transaction()` propia, para
 * que un `StockInsuficiente` en la segunda línea de repuestos revierta
 * también la primera (ver `MaquinaEstadosOrdenMantenimiento::cerrar()`).
 */
interface EscrituraConsumoStock
{
    /**
     * Descuenta `$cantidad` de `inv_stock` para `$repuestoId` en `$baseId`.
     *
     * @return string costo total aplicado (costo_unitario vigente del
     *                repuesto × `$cantidad`, invariante 6 de CLAUDE.md:
     *                nunca float).
     *
     * @throws StockInsuficiente si el stock disponible no alcanza.
     */
    public function consumir(int $repuestoId, int $baseId, string $cantidad, ?int $ordenMantenimientoId): string;
}
