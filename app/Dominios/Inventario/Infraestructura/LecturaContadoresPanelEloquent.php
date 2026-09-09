<?php

namespace App\Dominios\Inventario\Infraestructura;

use App\Dominios\Inventario\Contratos\LecturaContadoresPanel;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;

/**
 * Implementación Eloquent del contrato de contadores de panel de Inventario
 * (TE-14, tarea 60). Vive fuera de `Infraestructura/Eloquent/` porque no es
 * un modelo — es el adaptador que el `ServiceProvider` liga al contrato.
 */
final class LecturaContadoresPanelEloquent implements LecturaContadoresPanel
{
    public function stockBajoMinimo(): int
    {
        return Stock::query()
            ->whereColumn('cantidad', '<=', 'stock_minimo')
            ->count();
    }
}
