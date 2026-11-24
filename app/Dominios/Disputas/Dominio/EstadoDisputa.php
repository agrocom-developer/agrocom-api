<?php

namespace App\Dominios\Disputas\Dominio;

/**
 * Estado de una disputa (especificación §5.5). Solo `MaquinaEstadosDisputa` lo
 * cambia, consultando {@see TransicionesDisputa}.
 */
enum EstadoDisputa: string
{
    case Abierta = 'abierta';
    case EnRevision = 'en_revision';
    case Resuelta = 'resuelta';
    case Retirada = 'retirada';

    /** La disputa admite argumentos de las partes y la decisión del administrador. */
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
