<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `com_lotes_codigo_unico` (HU-24): un código de lote no puede repetirse
 * entre lotes ACTIVOS del mismo campo. El caso de uso que persiste `Lote`
 * captura la `QueryException` y la relanza como esta excepción — nunca deja
 * propagarse el 500 crudo del motor de base de datos. Mismo criterio que
 * `ClienteDuplicado`.
 */
final class LoteDuplicado extends RuntimeException
{
    public static function porCodigo(string $codigo): self
    {
        return new self("Ya existe un lote activo con el código '{$codigo}' para este campo.");
    }
}
