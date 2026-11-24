<?php

namespace App\Dominios\Calificaciones\Dominio;

use DateTimeImmutable;

/**
 * El plazo para calificar un servicio: 7 días desde que quedó `completada`
 * (ADR 0031, punto 5). Pura.
 */
final class PlazoDeCalificacion
{
    public static function venceEn(DateTimeImmutable $completadaEn, int $dias): DateTimeImmutable
    {
        return $completadaEn->modify("+{$dias} days");
    }

    public static function estaAbierto(DateTimeImmutable $completadaEn, DateTimeImmutable $ahora, int $dias): bool
    {
        return $ahora <= self::venceEn($completadaEn, $dias);
    }
}
