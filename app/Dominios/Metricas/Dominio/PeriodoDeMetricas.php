<?php

namespace App\Dominios\Metricas\Dominio;

use DateTimeImmutable;

/**
 * La ventana de tiempo de las métricas del mercado: los últimos 7, 30 o 90 días
 * (por defecto 30). Pura: `desde` y `hasta` se calculan del instante que se le da.
 */
final readonly class PeriodoDeMetricas
{
    /** @var list<int> */
    public const DIAS_VALIDOS = [7, 30, 90];

    public const DIAS_POR_DEFECTO = 30;

    private function __construct(public int $dias, public DateTimeImmutable $desde, public DateTimeImmutable $hasta) {}

    public static function de(?string $clave, DateTimeImmutable $ahora): self
    {
        $dias = (int) $clave;
        $dias = in_array($dias, self::DIAS_VALIDOS, true) ? $dias : self::DIAS_POR_DEFECTO;

        return new self($dias, $ahora->modify("-{$dias} days"), $ahora);
    }
}
