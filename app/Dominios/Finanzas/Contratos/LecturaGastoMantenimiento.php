<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Contrato de LECTURA de `Finanzas` para el precio final real de una orden de
 * mantenimiento cerrada (HU-88, tarea 103; ADR 0003 regla 2). `Mantenimiento`
 * no lee el modelo Eloquent `Gasto` directamente (ver docblock de
 * `OrdenMantenimiento`) — solo conoce `gasto_id`, y necesita el monto real
 * para mostrarlo en el detalle de la orden en vez de solo el número de gasto.
 *
 * Mismo patrón que {@see EscrituraGastoMantenimiento} (que ya cruza esta
 * frontera en sentido contrario, al cerrar la orden), pero de lectura.
 */
interface LecturaGastoMantenimiento
{
    /**
     * Monto del gasto `$gastoId`, o `null` si no existe. DECIMAL como string
     * (invariante 6 de CLAUDE.md: nunca float), calculado siempre desde
     * `fin_gastos.monto` — nunca recalculado ni cacheado del lado de
     * `Mantenimiento`.
     */
    public function montoDe(int $gastoId): ?string;
}
