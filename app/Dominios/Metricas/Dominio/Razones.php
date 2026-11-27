<?php

namespace App\Dominios\Metricas\Dominio;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Las cuentas de las métricas, en decimal exacto: una tasa es `parte / total` con
 * cuatro decimales (entre 0 y 1) y un monto es una suma o un cociente a dos
 * decimales. Sin divisor no hay tasa: devuelven `null`, no cero, para que el panel
 * muestre «sin datos» y no un 0 % que parece una medición. Puras.
 */
final class Razones
{
    /** @return string|null `parte / total` a cuatro decimales, o `null` si `total` es cero. */
    public static function tasa(int $parte, int $total): ?string
    {
        if ($total <= 0) {
            return null;
        }

        return (string) BigDecimal::of($parte)->dividedBy($total, 4, RoundingMode::HalfUp);
    }

    /** @return string|null `parte / total` a cuatro decimales para dos importes, o `null` si `total` es cero. */
    public static function tasaDeImportes(string $parte, string $total): ?string
    {
        $divisor = BigDecimal::of($total);

        if ($divisor->isZero()) {
            return null;
        }

        return (string) BigDecimal::of($parte)->dividedBy($divisor, 4, RoundingMode::HalfUp);
    }

    /** @return string|null `parte / total` a dos decimales, o `null` si `total` es cero. */
    public static function cociente(string $parte, string $total): ?string
    {
        $divisor = BigDecimal::of($total);

        if ($divisor->isZero()) {
            return null;
        }

        return (string) BigDecimal::of($parte)->dividedBy($divisor, 2, RoundingMode::HalfUp);
    }

    /**
     * @param  list<int|float>  $valores
     * @return string|null el promedio a un decimal, o `null` sin valores.
     */
    public static function promedio(array $valores): ?string
    {
        if ($valores === []) {
            return null;
        }

        return (string) BigDecimal::of((string) array_sum($valores))->dividedBy(count($valores), 1, RoundingMode::HalfUp);
    }

    /**
     * @param  list<int|float>  $valores
     * @return string|null la mediana a un decimal, o `null` sin valores.
     */
    public static function mediana(array $valores): ?string
    {
        if ($valores === []) {
            return null;
        }

        sort($valores);
        $mitad = intdiv(count($valores), 2);
        $mediana = count($valores) % 2 === 1
            ? BigDecimal::of((string) $valores[$mitad])
            : BigDecimal::of((string) $valores[$mitad - 1])->plus(BigDecimal::of((string) $valores[$mitad]))->dividedBy(2, 4, RoundingMode::HalfUp);

        return (string) $mediana->toScale(1, RoundingMode::HalfUp);
    }

    /** Un importe o una suma que devuelve la base (cadena, número o `null`) a la escala dada, sin pasar por `float`. */
    public static function escalar(mixed $valor, int $escala): string
    {
        return (string) BigDecimal::of($valor === null || $valor === '' ? '0' : (string) $valor)->toScale($escala, RoundingMode::HalfUp);
    }

    /** Suma en decimal exacto de importes que llegan como cadena, número o `null`. */
    public static function suma(mixed ...$importes): string
    {
        $total = BigDecimal::zero();

        foreach ($importes as $importe) {
            $total = $total->plus(BigDecimal::of($importe === null || $importe === '' ? '0' : (string) $importe));
        }

        return (string) $total->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * Cuántos valores caen en cada tramo. `$limites` son los extremos superiores, exclusivos y
     * ascendentes, de todos los tramos menos el último, que no tiene tope: con `[60, 360]` los tramos
     * son «menos de 60», «de 60 a menos de 360» y «360 o más». Devuelve un conteo por tramo.
     *
     * @param  list<int|float>  $valores
     * @param  list<int|float>  $limites
     * @return list<int>
     */
    public static function distribuir(array $valores, array $limites): array
    {
        $conteos = array_fill(0, count($limites) + 1, 0);

        foreach ($valores as $valor) {
            $tramo = 0;
            while ($tramo < count($limites) && $valor >= $limites[$tramo]) {
                $tramo++;
            }
            $conteos[$tramo]++;
        }

        return $conteos;
    }
}
