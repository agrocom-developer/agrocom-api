<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use App\Dominios\Finanzas\Dominio\EstadoRendicion;
use App\Dominios\Finanzas\Dominio\MaquinaEstados\TransicionesRendicion;
use DomainException;

/**
 * La rendición referenciada por `POST /panel/rendiciones/{rendicion}/aprobar`
 * no está en `Presentada` (HU-34, tarea 48) — la guarda de negocio de
 * `Aplicacion/AprobarRendicion`, que se apoya en {@see TransicionesRendicion}
 * para decidirlo, mismo reparto que `PlanillaNoAprobable` frente a
 * `TransicionesPlanilla`.
 */
final class RendicionNoAprobable extends DomainException
{
    public static function porTransicionInvalida(int $rendicionId, EstadoRendicion $estadoActual): self
    {
        return new self(
            "La rendición #{$rendicionId} no se puede aprobar: está en '{$estadoActual->value}', no en 'presentada'.",
        );
    }
}
