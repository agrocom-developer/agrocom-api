<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Operaciones\Dominio\EstadoActa;
use DomainException;

/**
 * La transición pedida no está en {@see
 * \App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesActa} — p. ej.
 * intentar firmar un acta que ya está firmada con una evidencia distinta.
 */
final class TransicionActaNoPermitida extends DomainException
{
    public static function entre(EstadoActa $desde, EstadoActa $hasta): self
    {
        return new self(
            "No se puede pasar un acta de '{$desde->value}' a '{$hasta->value}'.",
        );
    }
}
