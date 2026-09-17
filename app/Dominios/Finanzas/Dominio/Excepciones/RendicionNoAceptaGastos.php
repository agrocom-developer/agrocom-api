<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
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
        return new self(Texto::de('finanzas.errores.rendicion_no_acepta_gastos', ['rendicion_id' => $rendicionId]));
    }
}
