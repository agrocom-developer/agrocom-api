<?php

namespace App\Dominios\Asignacion\Dominio;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Puntuación con que se ordena a terceros e independientes (especificación
 * §6.6, ADR 0030): `0.40 × cercanía + 0.35 × calificación + 0.15 × aceptación
 * + 0.10 × (1 − cancelación)`, todo normalizado entre 0 y 1. Los pesos son
 * parámetros. Decimal exacto: dos proveedores con la misma puntuación empatan
 * siempre igual, sin ruido de punto flotante.
 */
final class PuntuacionDeTercero
{
    /**
     * @param  array{cercania: string, calificacion: string, aceptacion: string, cancelacion: string}  $pesos
     * @param  string|null  $distanciaKm  `null` si no se pudo medir: cercanía cero.
     * @param  string  $calificacion  promedio de 0 a 5; sin calificaciones se usa la neutra.
     */
    public static function calcular(
        array $pesos,
        ?string $distanciaKm,
        string $radioKm,
        string $calificacion,
        string $tasaAceptacion,
        string $tasaCancelacion,
    ): string {
        $cercania = BigDecimal::zero();

        $radio = BigDecimal::of($radioKm);

        if ($distanciaKm !== null && $radio->isPositive()) {
            $cercania = BigDecimal::one()->minus(BigDecimal::of($distanciaKm)->dividedBy($radio, 6, RoundingMode::HalfUp));
            $cercania = self::acotar($cercania);
        }

        $calificacionNormal = self::acotar(BigDecimal::of($calificacion)->dividedBy(5, 6, RoundingMode::HalfUp));
        $aceptacion = self::acotar(BigDecimal::of($tasaAceptacion));
        $noCancelacion = BigDecimal::one()->minus(self::acotar(BigDecimal::of($tasaCancelacion)));

        return BigDecimal::of($pesos['cercania'])->multipliedBy($cercania)
            ->plus(BigDecimal::of($pesos['calificacion'])->multipliedBy($calificacionNormal))
            ->plus(BigDecimal::of($pesos['aceptacion'])->multipliedBy($aceptacion))
            ->plus(BigDecimal::of($pesos['cancelacion'])->multipliedBy($noCancelacion))
            ->toScale(4, RoundingMode::HalfUp)
            ->__toString();
    }

    private static function acotar(BigDecimal $valor): BigDecimal
    {
        if ($valor->isNegative()) {
            return BigDecimal::zero();
        }

        return $valor->isGreaterThan(BigDecimal::one()) ? BigDecimal::one() : $valor;
    }
}
