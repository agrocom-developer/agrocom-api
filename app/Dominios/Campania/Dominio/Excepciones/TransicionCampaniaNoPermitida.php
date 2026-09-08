<?php

namespace App\Dominios\Campania\Dominio\Excepciones;

use App\Dominios\Campania\Dominio\EstadoCampania;
use DomainException;

/**
 * La transición pedida no está en {@see
 * \App\Dominios\Campania\Dominio\MaquinaEstados\TransicionesCampania} — p.
 * ej. intentar reabrir una campaña `cerrada`, mismo criterio que
 * `TransicionContratoNoPermitida` en Comercial.
 */
final class TransicionCampaniaNoPermitida extends DomainException
{
    public static function entre(EstadoCampania $desde, EstadoCampania $hasta): self
    {
        return new self(
            "No se puede pasar una campaña de '{$desde->value}' a '{$hasta->value}'.",
        );
    }
}
