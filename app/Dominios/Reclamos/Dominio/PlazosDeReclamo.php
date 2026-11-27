<?php

namespace App\Dominios\Disputas\Dominio;

use DateTimeImmutable;

/**
 * Los plazos de la política de disputas: el cliente abre hasta 72 horas después
 * de `ejecutada`, el proveedor dentro de su ventana, la contraparte responde en 48
 * horas y las partes apelan en 7 días desde la resolución. Pura.
 */
final class PlazosDeDisputa
{
    public static function clientePuedeAbrir(DateTimeImmutable $ejecutadaEn, DateTimeImmutable $ahora, int $horas): bool
    {
        return $ahora <= $ejecutadaEn->modify("+{$horas} hours");
    }

    public static function proveedorPuedeAbrir(?DateTimeImmutable $desde, DateTimeImmutable $ahora, int $dias): bool
    {
        return $desde === null || $ahora <= $desde->modify("+{$dias} days");
    }

    public static function respondeAntesDe(DateTimeImmutable $ahora, int $horas): DateTimeImmutable
    {
        return $ahora->modify("+{$horas} hours");
    }

    public static function apelableHasta(DateTimeImmutable $resueltaEn, int $dias): DateTimeImmutable
    {
        return $resueltaEn->modify("+{$dias} days");
    }
}
