<?php

namespace App\Dominios\Inventario\Infraestructura\Http;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Cómo se muestra una cantidad o un costo DECIMAL en las pantallas de
 * Inventario: dos decimales, coma decimal y punto de miles ("1.234,50").
 *
 * La vista solo formatea (invariante 6 de CLAUDE.md): el valor llega como el
 * texto que guarda la base ("1234.5") y se redondea y agrupa sobre `BigDecimal`,
 * sin pasar nunca por `float`, así que lo que se ve es exactamente lo
 * guardado.
 */
final class FormatoCantidad
{
    /** "1234.5" → "1.234,50". Un valor vacío se muestra como el guion de las tablas. */
    public static function decimal(?string $valor, int $decimales = 2): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        $texto = (string) BigDecimal::of($valor)->toScale($decimales, RoundingMode::HalfUp);
        $negativo = str_starts_with($texto, '-');
        [$entero, $fraccion] = array_pad(explode('.', ltrim($texto, '-'), 2), 2, '');

        $entero = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $entero);

        return ($negativo ? '-' : '').$entero.($fraccion === '' ? '' : ','.$fraccion);
    }

    /** Con el signo explícito de un asiento que suma o resta: "+12,00" / "−3,00". */
    public static function conSigno(string $valor, bool $suma): string
    {
        return ($suma ? '+' : '−').self::decimal($valor);
    }
}
