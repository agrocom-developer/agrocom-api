<?php

namespace App\Dominios\Personal\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use DomainException;

/**
 * La transición pedida no está en `Dominio\MaquinaEstados\TransicionesEquipoTrabajo`
 * (tarea "cuadrillas-estadias", 19/9/2026) — hoy solo puede pasar si alguien
 * pide la MISMA transición dos veces seguidas (`activo → activo`), porque la
 * tabla solo tiene dos estados y ambos se alcanzan mutuamente. Mismo criterio
 * que `TransicionCampaniaNoPermitida`.
 */
final class TransicionEquipoTrabajoNoPermitida extends DomainException
{
    public static function entre(EstadoEquipoTrabajo $desde, EstadoEquipoTrabajo $hasta): self
    {
        return new self(
            Texto::de('personal.errores.transicion_equipo_trabajo_no_permitida', [
                'desde' => $desde->value,
                'hasta' => $hasta->value,
            ]),
        );
    }
}
