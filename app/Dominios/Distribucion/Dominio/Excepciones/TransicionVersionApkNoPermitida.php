<?php

namespace App\Dominios\Distribucion\Dominio\Excepciones;

use App\Dominios\Distribucion\Dominio\EstadoVersionApk;
use DomainException;

/**
 * La transición pedida no está en {@see
 * \App\Dominios\Distribucion\Dominio\TransicionesVersionApk} — p. ej. intentar
 * autorizar una versión ya rechazada sin pasarla antes por `Pendiente`.
 */
final class TransicionVersionApkNoPermitida extends DomainException
{
    public static function entre(EstadoVersionApk $desde, EstadoVersionApk $hasta): self
    {
        return new self(
            "No se puede pasar una versión de APK de '{$desde->value}' a '{$hasta->value}'.",
        );
    }
}
