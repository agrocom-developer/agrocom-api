<?php

namespace App\Dominios\Solicitudes\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use RuntimeException;

/** La solicitud no puede pasar de su estado actual al pedido (especificación §5.1). */
final class TransicionSolicitudNoPermitida extends RuntimeException
{
    public static function entre(EstadoSolicitud $desde, EstadoSolicitud $hacia): self
    {
        return new self(Texto::de('solicitudes.errores.transicion_no_permitida', [
            'desde' => Texto::de('solicitudes.estado.'.$desde->value),
            'hacia' => Texto::de('solicitudes.estado.'.$hacia->value),
        ]));
    }
}
