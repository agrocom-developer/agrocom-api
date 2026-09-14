<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use RuntimeException;

/**
 * Guarda de `AsignarEquiposOrden` (HU-92, tarea 107): un lote solo puede
 * recibir un `Trabajo` de esta orden si integra su lista de lotes
 * (`ope_orden_lotes`) — mismo criterio de defensa en profundidad que
 * `EquipoTrabajoNoVigente`: `AsignarEquipoOrdenRequest` ya lo valida por
 * forma (`lotes.*.lote_id` contra `ope_orden_lotes` de esa orden), esta
 * excepción cubre un `ejecutar()` invocado sin pasar por ese Request.
 */
final class LoteNoPerteneceAOrden extends RuntimeException
{
    public static function porLote(int $loteId, int $ordenId): self
    {
        return new self("El lote #{$loteId} no pertenece a la orden #{$ordenId}.");
    }
}
