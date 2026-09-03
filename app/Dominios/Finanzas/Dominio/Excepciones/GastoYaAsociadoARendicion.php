<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use DomainException;

/**
 * El gasto que `Aplicacion/AsociarGastoARendicion` intenta asociar ya tiene
 * `rendicion_id` (HU-34, tarea 48) — un gasto pertenece, a lo sumo, a una
 * única rendición; reasociarlo requeriría desasociarlo primero, y esta tarea
 * no ofrece ese caso de uso (mismo criterio de "sin edición" que el resto del
 * módulo: `Gasto`/`Anticipo` son inmutables salvo baja).
 */
final class GastoYaAsociadoARendicion extends DomainException
{
    public static function paraGasto(int $gastoId): self
    {
        return new self("El gasto #{$gastoId} ya está asociado a una rendición.");
    }
}
