<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
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
            Texto::de('finanzas.errores.rendicion_no_aprobable', [
                'rendicion_id' => $rendicionId,
                'estado_actual' => $estadoActual->value,
            ]),
        );
    }
}
