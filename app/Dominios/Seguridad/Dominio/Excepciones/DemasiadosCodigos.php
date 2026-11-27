<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/** La cuenta ya pidió el máximo de códigos por hora o por día (ADR 0046, punto 27): `429`. */
final class DemasiadosCodigos extends RuntimeException
{
    public function __construct(public readonly int $segundos)
    {
        parent::__construct(Texto::de('seguridad.codigos.demasiados'));
    }
}
