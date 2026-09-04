<?php

namespace App\Dominios\Inventario\Contratos;

/**
 * Frontera de lectura de Inventario hacia el panel (ADR 0003, regla 2; TE-14,
 * tarea 60): el contador real que alimenta el badge `menu.mantenimiento.items.stock`
 * (`CascaraPanel`, módulo `Seguridad`) — nunca el modelo Eloquent `Stock`
 * cruzando la frontera.
 */
interface LecturaContadoresPanel
{
    /** Filas de `inv_stock` con `cantidad <= stock_minimo` (mismo criterio que `ListarStock::$alerta`). */
    public function stockBajoMinimo(): int;
}
