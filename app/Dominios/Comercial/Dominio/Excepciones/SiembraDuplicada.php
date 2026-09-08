<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `com_lote_campania_lote_campania_unico` (HU-48, tarea 71, etapa 2): un
 * lote no puede tener dos siembras vigentes en la misma campaña. El caso de
 * uso que persiste `LoteCampania` captura la `QueryException` y la relanza
 * como esta excepción — nunca deja propagarse el 500 crudo del motor de
 * base de datos. Mismo criterio que `CultivoDuplicado`/`LoteDuplicado`.
 */
final class SiembraDuplicada extends RuntimeException
{
    public static function paraLote(string $codigoLote): self
    {
        return new self("El lote '{$codigoLote}' ya tiene una siembra cargada para esta campaña.");
    }
}
