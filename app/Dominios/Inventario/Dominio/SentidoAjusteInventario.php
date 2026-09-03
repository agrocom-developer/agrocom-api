<?php

namespace App\Dominios\Inventario\Dominio;

/**
 * Signo de un movimiento `tipo=ajuste` (HU-36, tarea 52;
 * `inv_movimientos.sentido`, CHECK en la migración) — el único de los cuatro
 * tipos que puede sumar o restar stock. Para los otros tres tipos `sentido`
 * queda NULL: `TipoMovimientoInventario` ya determina el signo sin
 * ambigüedad. Ver docblock de
 * `database/migrations/2026_09_03_300003_create_inv_movimientos_table.php`.
 */
enum SentidoAjusteInventario: string
{
    case Incremento = 'incremento';
    case Decremento = 'decremento';
}
