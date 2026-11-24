<?php

namespace App\Dominios\Asignacion\Dominio;

/**
 * Estado de una oferta (especificación del marketplace §5.3). Solo
 * {@see TransicionesOferta} dice qué pasos admite.
 */
enum EstadoOferta: string
{
    case Ofrecida = 'ofrecida';
    case Aceptada = 'aceptada';
    case Rechazada = 'rechazada';
    case Vencida = 'vencida';
    case Retirada = 'retirada';

    /** Una oferta viva todavía espera la respuesta del proveedor. */
    public function esViva(): bool
    {
        return $this === self::Ofrecida;
    }

    /** Tono del badge del panel (`atoms/badge`). */
    public function tono(): string
    {
        return match ($this) {
            self::Ofrecida => 'info',
            self::Aceptada => 'success',
            self::Rechazada => 'danger',
            self::Vencida => 'warning',
            self::Retirada => 'neutral',
        };
    }
}
