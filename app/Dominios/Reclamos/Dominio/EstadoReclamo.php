<?php

namespace App\Dominios\Reclamos\Dominio;

/**
 * Estado de un reclamo (especificación §5.5). Solo `MaquinaEstadosReclamo` lo
 * cambia, consultando {@see TransicionesReclamo}.
 */
enum EstadoReclamo: string
{
    case Abierta = 'abierta';
    case EnRevision = 'en_revision';
    case Resuelta = 'resuelta';
    case Retirada = 'retirada';

    /** El reclamo admite argumentos de las partes y la decisión del administrador. */
    public function estaViva(): bool
    {
        return $this === self::Abierta || $this === self::EnRevision;
    }

    public function tono(): string
    {
        return match ($this) {
            self::Abierta => 'warning',
            self::EnRevision => 'info',
            self::Resuelta => 'success',
            self::Retirada => 'neutral',
        };
    }
}
