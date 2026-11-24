<?php

namespace App\Dominios\Compartido\Dominio\Excepciones;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * La acción no corresponde al estado actual del sujeto (por ejemplo, aprobar
 * a quien ya está aprobado o rehabilitar a quien está dado de baja).
 */
final class TransicionDeAprobacionInvalida extends RuntimeException
{
    public static function desde(EstadoAprobacion $estado, AccionAprobacion $accion): self
    {
        return new self(Texto::de('aprobacion.errores.transicion_invalida', [
            'estado' => Texto::de('aprobacion.estado.'.$estado->value),
            'accion' => mb_strtolower(Texto::de('aprobacion.accion.'.$accion->value)),
        ]));
    }
}
