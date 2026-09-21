<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
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
        return new self(Texto::de('operaciones.errores.transicion_trabajo_no_permitida', [
            'desde' => $desde->value,
            'hasta' => $hasta->value,
        ]));
    }
}
