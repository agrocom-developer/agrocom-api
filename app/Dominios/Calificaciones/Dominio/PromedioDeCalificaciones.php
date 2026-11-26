<?php

namespace App\Dominios\Calificaciones\Dominio;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * El promedio de una reputación (política de calificación, «Promedio y umbrales»),
 * en decimal exacto. La reputación de un proveedor es el promedio ponderado de lo
 * que recibe él y su piloto, con un peso por tipo de sujeto (ADR 0039);
 * la de un cliente es el promedio simple de lo que le dan los proveedores. Pura.
 */
final class PromedioDeCalificaciones
{
    /**
     * @param  list<array{sujeto: string, estrellas: int}>  $calificaciones
     * @param  array<string, string>  $pesos  peso por tipo de sujeto (`proveedor`, `piloto`); un tipo sin peso no cuenta.
     * @return string promedio a dos decimales, o `0.00` si no hay nada que ponderar.
     */
    public static function ponderado(array $calificaciones, array $pesos): string
    {
        $suma = BigDecimal::zero();
        $divisor = BigDecimal::zero();

        foreach ($calificaciones as $calificacion) {
            $peso = BigDecimal::of($pesos[$calificacion['sujeto']] ?? '0');

            $suma = $suma->plus($peso->multipliedBy($calificacion['estrellas']));
            $divisor = $divisor->plus($peso);
        }

        if ($divisor->isZero()) {
            return '0.00';
        }

        return (string) $suma->dividedBy($divisor, 2, RoundingMode::HalfUp);
    }

    /**
     * @param  list<int>  $estrellas
     * @return string promedio a dos decimales, o `0.00` sin calificaciones.
     */
    public static function simple(array $estrellas): string
    {
        if ($estrellas === []) {
            return '0.00';
        }

        return (string) BigDecimal::of(array_sum($estrellas))->dividedBy(count($estrellas), 2, RoundingMode::HalfUp);
    }
}
