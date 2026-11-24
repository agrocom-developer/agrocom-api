<?php

namespace App\Dominios\Compartido\Dominio\Excepciones;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/** Rechazar, suspender o dar de baja sin motivo (ADR 0032, punto 5). */
final class MotivoRequerido extends RuntimeException
{
    public static function para(AccionAprobacion $accion): self
    {
        return new self(Texto::de('aprobacion.errores.motivo_requerido', [
            'accion' => mb_strtolower(Texto::de('aprobacion.accion.'.$accion->value)),
        ]));
    }
}
