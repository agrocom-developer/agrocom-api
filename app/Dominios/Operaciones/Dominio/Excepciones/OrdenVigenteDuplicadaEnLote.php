<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use RuntimeException;

/**
 * Un lote no puede tener dos órdenes VIGENTES a la vez — sin orden vigente
 * no se abre trabajo; con dos vigentes no se sabría cuál rige. Hasta HU-70
 * lo garantizaba el índice único parcial `ope_ordenes_aplicacion_lote_vigente_unico`
 * y esta excepción traducía la `QueryException` de esa violación. Desde
 * HU-92 (tarea 107, orden con N lotes) la regla cruza `ope_orden_lotes` con
 * `ope_ordenes_aplicacion.estado` — un índice parcial no puede condicionar
 * sobre una tabla ajena — así que `MaquinaEstadosOrden::activar()` la
 * verifica de forma explícita (con lock, ver su docblock) y lanza esta
 * excepción directo, sin pasar por una `QueryException`. Mismo criterio que
 * `DronDuplicado`: nunca dejar propagarse el 500 crudo del motor de base de
 * datos.
 */
final class OrdenVigenteDuplicadaEnLote extends RuntimeException
{
    public static function porLote(int $loteId): self
    {
        return new self("El lote #{$loteId} ya tiene otra orden de aplicación vigente.");
    }
}
