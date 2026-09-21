<?php

namespace App\Dominios\Inventario\Dominio;

use Brick\Math\BigDecimal;

/**
 * Regla de reposición del stock (HU-36, tarea 52): en qué nivel está una fila
 * de `inv_stock`. Vive una sola vez para que el listado de stock, su franja de
 * KPI y el resumen del repuesto cuenten lo mismo — una fila que la tabla marca
 * no puede faltar en la cifra de arriba, ni contarse en dos.
 *
 * Los dos niveles son DISJUNTOS a propósito: una fila sin nada es «sin
 * existencias», no «bajo el mínimo». Con un mínimo sin definir (0) una fila en
 * cero cruzaba «el mínimo» sin que nadie lo hubiera fijado; separarlas deja
 * cada cifra diciendo una sola cosa.
 *
 * Todo con `BigDecimal` sobre las cantidades DECIMAL tal como vienen de la
 * base (invariante 6 de CLAUDE.md): nunca `float`.
 */
final class NivelDeStock
{
    /** Sin nada en la base. El `CHECK (cantidad >= 0)` impide que sea negativa. */
    public static function sinExistencias(string $cantidad): bool
    {
        return BigDecimal::of($cantidad)->isZero();
    }

    /**
     * Queda algo, pero alcanzó o cruzó el punto de reposición. Con mínimo 0
     * (todavía sin definir) nunca se cumple: no hay nada que cruzar.
     */
    public static function bajoMinimo(string $cantidad, string $minimo): bool
    {
        $disponible = BigDecimal::of($cantidad);

        return $disponible->isPositive() && $disponible->isLessThanOrEqualTo(BigDecimal::of($minimo));
    }
}
