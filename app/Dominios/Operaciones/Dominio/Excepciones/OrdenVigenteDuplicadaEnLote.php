<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `ope_ordenes_aplicacion_lote_vigente_unico` (HU-25, tarea 38): un lote no
 * puede tener dos órdenes VIGENTES a la vez — sin orden vigente no se abre
 * trabajo; con dos vigentes no se sabría cuál rige (ver docblock de la
 * migración `create_ope_ordenes_aplicacion_table`). `MaquinaEstadosOrden::activar()`
 * captura la `QueryException` que dispara esa violación y la relanza como
 * esta excepción — nunca deja propagarse el 500 crudo del motor de base de
 * datos. Mismo criterio que `DronDuplicado`.
 */
final class OrdenVigenteDuplicadaEnLote extends RuntimeException
{
    public static function porLote(int $loteId): self
    {
        return new self("El lote #{$loteId} ya tiene otra orden de aplicación vigente.");
    }
}
