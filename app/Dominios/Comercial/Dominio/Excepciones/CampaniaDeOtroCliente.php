<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use RuntimeException;

/**
 * Guarda central de HU-46 (ADR 0015 punto 1, corregido el 8/9/2026): un
 * contrato no puede imputarse a una campaña cuyo `cliente_id` no sea el
 * propio `cliente_id` del contrato — sin esto el modelo permite facturarle a
 * un cliente el ciclo productivo de otro. La verifican
 * `Aplicacion/CrearContrato` y `Aplicacion/ActualizarContrato` leyendo
 * `cpn_campanias` por FK plana (ADR 0003 regla 3, sin `belongsTo`
 * cross-módulo).
 */
final class CampaniaDeOtroCliente extends RuntimeException
{
    public static function paraCampania(string $codigo): self
    {
        return new self("La campaña '{$codigo}' pertenece a otro cliente.");
    }
}
