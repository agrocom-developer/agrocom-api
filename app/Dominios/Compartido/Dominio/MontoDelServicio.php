<?php

namespace App\Dominios\Compartido\Dominio;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Cuentas exactas del servicio (invariante 6 de CLAUDE.md; `negociacion-contrato.md`
 * §3.1): el monto es hectáreas por precio acordado, redondeado a 2 decimales
 * mitad hacia arriba solo al presentarlo o congelarlo. Nada pasa por `float`.
 */
final class MontoDelServicio
{
    /** `hectareas × precio_ha`, a 2 decimales. */
    public static function monto(string $hectareas, string $precioHa): string
    {
        return (string) BigDecimal::of($hectareas)
            ->multipliedBy(BigDecimal::of($precioHa))
            ->toScale(2, RoundingMode::HalfUp);
    }

    /** Máximo de hectáreas ejecutables: las solicitadas más la tolerancia. */
    public static function tope(string $solicitadas, string $toleranciaPct): string
    {
        return (string) BigDecimal::of($solicitadas)
            ->multipliedBy(BigDecimal::of('100')->plus(BigDecimal::of($toleranciaPct)))
            ->dividedBy(100, 4, RoundingMode::HalfUp);
    }

    /** Porcentaje (0 a 100, 2 decimales) que son `ejecutadas` de `solicitadas`, acotado a 100. */
    public static function porcentaje(string $ejecutadas, string $solicitadas): string
    {
        $solicitadas = BigDecimal::of($solicitadas);
        if ($solicitadas->isZero()) {
            return '0.00';
        }

        $valor = BigDecimal::of($ejecutadas)->multipliedBy(100)->dividedBy($solicitadas, 2, RoundingMode::HalfUp);

        return (string) ($valor->isGreaterThan(100) ? BigDecimal::of('100.00') : $valor)->toScale(2, RoundingMode::HalfUp);
    }

    /** Hectáreas (4 decimales) que corresponden a un porcentaje de las solicitadas. */
    public static function hectareasDePorcentaje(string $porcentaje, string $solicitadas): string
    {
        return (string) BigDecimal::of($solicitadas)
            ->multipliedBy(BigDecimal::of($porcentaje))
            ->dividedBy(100, 4, RoundingMode::HalfUp);
    }
}
