<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Seguridad\Dominio\EstadoDeEliminacion;
use LogicException;

/** La solicitud de eliminación no puede pasar de ese estado al pedido (ADR 0047): es un error de programación, no de la persona. */
final class TransicionDeEliminacionInvalida extends LogicException
{
    public static function entre(EstadoDeEliminacion $desde, EstadoDeEliminacion $hacia): self
    {
        return new self("Una eliminación {$desde->value} no pasa a {$hacia->value}.");
    }
}
