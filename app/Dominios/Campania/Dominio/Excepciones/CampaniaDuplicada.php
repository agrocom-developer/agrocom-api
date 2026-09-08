<?php

namespace App\Dominios\Campania\Dominio\Excepciones;

use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `cpn_campanias_codigo_unico`: un código de campaña no puede repetirse
 * entre campañas ACTIVAS. El caso de uso que persiste `Campania` captura la
 * `QueryException` y la relanza como esta excepción — nunca deja propagarse
 * el 500 crudo del motor de base de datos. Mismo criterio que `CampoDuplicado`
 * en Comercial.
 */
final class CampaniaDuplicada extends RuntimeException
{
    public static function porCodigo(string $codigo): self
    {
        return new self("Ya existe una campaña activa con el código '{$codigo}'.");
    }
}
