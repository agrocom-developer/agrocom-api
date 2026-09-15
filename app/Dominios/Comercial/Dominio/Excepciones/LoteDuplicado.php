<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `com_lotes_codigo_unico` (HU-24; ADR 0020 — el índice pasó a ser
 * `(propiedad_id, codigo)`): un código de lote no puede repetirse entre
 * lotes ACTIVOS de la misma propiedad. El caso de uso que persiste `Lote`
 * captura la `QueryException` y la relanza como esta excepción — nunca deja
 * propagarse el 500 crudo del motor de base de datos. Mismo criterio que
 * `ClienteDuplicado`.
 */
final class LoteDuplicado extends RuntimeException
{
    public static function porCodigo(string $codigo): self
    {
        return new self("Ya existe un lote activo con el código '{$codigo}' para esta propiedad.");
    }
}
