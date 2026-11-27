<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * La cuenta ya pidió el máximo de códigos por hora o por día (`cupo`), o pidió otro por el mismo canal hace menos del
 * intervalo mínimo (`intervalo`) (ADR 0046, punto 27): `429` con `Retry-After` en `$segundos`.
 */
final class DemasiadosCodigos extends RuntimeException
{
    public const CUPO = 'cupo';

    public const INTERVALO = 'intervalo';

    /** @param  int  $segundos  cuánto falta para poder pedir otro código (siempre positivo). */
    public function __construct(public readonly int $segundos, public readonly string $motivo = self::CUPO)
    {
        parent::__construct(Texto::de($motivo === self::INTERVALO ? 'seguridad.codigos.reenvio_pronto' : 'seguridad.codigos.demasiados'));
    }
}
