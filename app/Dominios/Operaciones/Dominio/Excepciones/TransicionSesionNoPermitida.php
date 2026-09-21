<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use DomainException;

/**
 * La transición pedida no está en {@see
 * \App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesSesion} —
 * p. ej. intentar cerrar una sesión que ya está cerrada.
 */
final class TransicionSesionNoPermitida extends DomainException
{
    public static function entre(EstadoSesion $desde, EstadoSesion $hasta): self
    {
        return new self(Texto::de('operaciones.errores.transicion_sesion_no_permitida', [
            'desde' => $desde->value,
            'hasta' => $hasta->value,
        ]));
    }
}
