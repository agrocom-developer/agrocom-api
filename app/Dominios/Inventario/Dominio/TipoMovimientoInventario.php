<?php

namespace App\Dominios\Inventario\Dominio;

/**
 * Tipo de movimiento de stock (HU-36, tarea 52; `inv_movimientos.tipo`,
 * CHECK en la migración). Determina el signo que aplica a `inv_stock` en
 * `RegistrarMovimientoStock`:
 * - `Compra`: incrementa la base afectada.
 * - `Salida`: decrementa la base afectada (nunca deja el stock negativo).
 * - `Ajuste`: incrementa o decrementa según `SentidoAjusteInventario`.
 * - `Traslado`: decrementa la base de origen e incrementa la de destino, en
 *   la misma transacción.
 */
enum TipoMovimientoInventario: string
{
    case Compra = 'compra';
    case Salida = 'salida';
    case Ajuste = 'ajuste';
    case Traslado = 'traslado';
}
