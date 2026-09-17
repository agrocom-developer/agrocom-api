<?php

namespace App\Dominios\Mantenimiento\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;
use DomainException;

/**
 * La transición pedida no está en {@see
 * \App\Dominios\Mantenimiento\Dominio\MaquinaEstados\TransicionesOrdenMantenimiento}
 * — p. ej. intentar cerrar una orden que ya está `cerrada`.
 */
final class TransicionOrdenMantenimientoNoPermitida extends DomainException
{
    public static function entre(EstadoOrdenMantenimiento $desde, EstadoOrdenMantenimiento $hasta): self
    {
        return new self(
            Texto::de('mantenimiento.errores.transicion_orden_no_permitida', [
                'desde' => $desde->value,
                'hasta' => $hasta->value,
            ]),
        );
    }
}
