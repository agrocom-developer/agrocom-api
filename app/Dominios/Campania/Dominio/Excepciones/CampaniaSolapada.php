<?php

namespace App\Dominios\Campania\Dominio\Excepciones;

use DomainException;

/**
 * Guarda de datos al abrir una campaña (ADR 0015 punto 1): no puede haber
 * otra campaña `abierta` cuyo rango de fechas se solape con el de la que se
 * quiere abrir. Sin esto, dos campañas abiertas simultáneas sobre las mismas
 * fechas dejarían ambiguo a qué campaña se imputa un contrato o un gasto
 * nuevo.
 */
final class CampaniaSolapada extends DomainException
{
    public static function con(string $codigoOtra): self
    {
        return new self(
            "El rango de fechas se solapa con la campaña abierta '{$codigoOtra}'.",
        );
    }
}
