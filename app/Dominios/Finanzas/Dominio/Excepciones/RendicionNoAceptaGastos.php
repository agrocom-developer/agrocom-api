<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use DomainException;

/**
 * Se intentó asociar un gasto a una rendición que ya no está `Abierta`
 * (HU-34, tarea 48) — una vez `Presentada`, el `monto` de la rendición ya
 * quedó calculado y firmado por el jefe de campo; sumarle un gasto nuevo por
 * detrás lo desalinearía. `Aplicacion/AsociarGastoARendicion` la lanza antes
 * de tocar el gasto.
 */
final class RendicionNoAceptaGastos extends DomainException
{
    public static function paraRendicion(int $rendicionId): self
    {
        return new self("La rendición #{$rendicionId} no acepta gastos: ya no está 'abierta'.");
    }
}
