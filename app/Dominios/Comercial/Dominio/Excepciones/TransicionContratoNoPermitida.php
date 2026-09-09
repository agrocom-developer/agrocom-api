<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Comercial\Dominio\EstadoContrato;
use DomainException;

/**
 * La transición pedida no está en {@see
 * \App\Dominios\Comercial\Dominio\MaquinaEstados\TransicionesContrato} — p.
 * ej. intentar reactivar un contrato `finalizado`, o cancelar uno que ya está
 * `cancelado`.
 */
final class TransicionContratoNoPermitida extends DomainException
{
    public static function entre(EstadoContrato $desde, EstadoContrato $hasta): self
    {
        return new self(
            "No se puede pasar un contrato de '{$desde->value}' a '{$hasta->value}'.",
        );
    }
}
