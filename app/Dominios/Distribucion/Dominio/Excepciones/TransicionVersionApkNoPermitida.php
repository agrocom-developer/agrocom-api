<?php

namespace App\Dominios\Distribucion\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
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
            Texto::de('distribucion.errores.transicion_version_apk_no_permitida', [
                'desde' => $desde->value,
                'hasta' => $hasta->value,
            ]),
        );
    }
}
