<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use App\Dominios\Finanzas\Dominio\EstadoRendicion;
use App\Dominios\Finanzas\Dominio\MaquinaEstados\TransicionesRendicion;
use DomainException;

/**
 * La rendición referenciada por `POST /panel/rendiciones/{rendicion}/presentar`
 * no puede pasar a `Presentada` (HU-34, tarea 48): o porque no está `Abierta`
 * — la guarda de `Aplicacion/PresentarRendicion`, que se apoya en {@see
 * TransicionesRendicion} para decidirlo, mismo reparto que
 * `PlanillaNoAprobable` frente a `TransicionesPlanilla` — o porque no tiene
 * ningún gasto asociado todavía: guarda de datos propios de la rendición,
 * vive DENTRO de la máquina de estados (`MaquinaEstadosRendicion::presentar()`),
 * mismo criterio que `MaquinaEstadosContrato::activar()`.
 */
final class RendicionNoPresentable extends DomainException
{
    public static function porTransicionInvalida(int $rendicionId, EstadoRendicion $estadoActual): self
    {
        return new self(
            "La rendición #{$rendicionId} no se puede presentar: está en '{$estadoActual->value}', no en 'abierta'.",
        );
    }

    public static function porSinGastosAsociados(int $rendicionId): self
    {
        return new self(
            "La rendición #{$rendicionId} no se puede presentar: no tiene ningún gasto asociado.",
        );
    }
}
