<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use DomainException;

/**
 * La transición pedida no está en {@see
 * \App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesTrabajo} —
 * p. ej. intentar cerrar un trabajo que ya está cerrado.
 */
final class TransicionTrabajoNoPermitida extends DomainException
{
    public static function entre(EstadoTrabajo $desde, EstadoTrabajo $hasta): self
    {
        return new self(
            "No se puede pasar un trabajo de '{$desde->value}' a '{$hasta->value}'.",
        );
    }
}
