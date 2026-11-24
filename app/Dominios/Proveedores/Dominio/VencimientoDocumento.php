<?php

namespace App\Dominios\Proveedores\Dominio;

use DateTimeImmutable;

/**
 * Qué le toca a un documento aprobado según su fecha de vencimiento (ADR 0032,
 * punto 4): avisos 30 y 7 días antes y, vencido, suspensión automática del
 * sujeto. Pura; el proceso diario decide cuándo avisar mirando los avisos ya
 * enviados.
 */
enum VencimientoDocumento: string
{
    case Vigente = 'vigente';
    case PorVencer30 = 'por_vencer_30';
    case PorVencer7 = 'por_vencer_7';
    case Vencido = 'vencido';

    public const int AVISO_PREVIO_LARGO = 30;

    public const int AVISO_PREVIO_CORTO = 7;

    public static function clasificar(?string $venceEn, DateTimeImmutable $hoy): self
    {
        if ($venceEn === null) {
            return self::Vigente;
        }

        $dias = self::diasHasta($venceEn, $hoy);

        return match (true) {
            $dias < 0 => self::Vencido,
            $dias <= self::AVISO_PREVIO_CORTO => self::PorVencer7,
            $dias <= self::AVISO_PREVIO_LARGO => self::PorVencer30,
            default => self::Vigente,
        };
    }

    /** Días enteros desde `$hoy` hasta `$venceEn` (negativo si ya pasó). */
    public static function diasHasta(string $venceEn, DateTimeImmutable $hoy): int
    {
        $vence = new DateTimeImmutable($venceEn.' 00:00:00', $hoy->getTimezone());
        $inicioHoy = $hoy->setTime(0, 0);

        return (int) $inicioHoy->diff($vence)->format('%r%a');
    }
}
