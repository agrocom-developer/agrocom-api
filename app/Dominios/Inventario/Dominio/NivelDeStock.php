<?php

namespace App\Dominios\Inventario\Dominio;

use Brick\Math\BigDecimal;

/**
 * Regla de reposición del stock (HU-36, tarea 52): cuándo una fila de
 * `inv_stock` está pidiendo reponerse. Vive una sola vez para que el listado
 * de stock, su franja de KPI y el resumen del repuesto cuenten lo mismo —
 * una fila que el listado marca no puede faltar en la cifra de arriba.
 *
 * Todo con `BigDecimal` sobre las cantidades DECIMAL tal como vienen de la
 * base (invariante 6 de CLAUDE.md): nunca `float`.
 */
final class NivelDeStock
{
    /**
     * Por debajo del mínimo: la cantidad alcanzó o cruzó el punto de
     * reposición. Con mínimo 0 (todavía sin definir) una fila en cero también
     * cuenta: no hay nada que consumir.
     */
    public static function bajoMinimo(string $cantidad, string $minimo): bool
    {
        return BigDecimal::of($cantidad)->isLessThanOrEqualTo(BigDecimal::of($minimo));
    }

    /** Sin nada en la base. El `CHECK (cantidad >= 0)` impide que sea negativa. */
    public static function sinExistencias(string $cantidad): bool
    {
        return BigDecimal::of($cantidad)->isZero();
    }
}
