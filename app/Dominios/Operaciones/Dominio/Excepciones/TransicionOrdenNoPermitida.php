<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use DomainException;

/**
 * La transición pedida no está en {@see
 * \App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesOrden} — p.
 * ej. intentar activar una orden que ya está `vigente`.
 */
final class TransicionOrdenNoPermitida extends DomainException
{
    public static function entre(EstadoOrdenAplicacion $desde, EstadoOrdenAplicacion $hasta): self
    {
        return new self(Texto::de('operaciones.errores.transicion_orden_no_permitida', [
            'desde' => $desde->value,
            'hasta' => $hasta->value,
        ]));
    }
}
