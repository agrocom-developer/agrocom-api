<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `com_campos_nombre_unico` (HU-24): un nombre de campo no puede repetirse
 * entre campos ACTIVOS del mismo cliente. El caso de uso que persiste
 * `Campo` captura la `QueryException` y la relanza como esta excepción —
 * nunca deja propagarse el 500 crudo del motor de base de datos. Mismo
 * criterio que `ClienteDuplicado`.
 */
final class CampoDuplicado extends RuntimeException
{
    public static function porNombre(string $nombre): self
    {
        return new self("Ya existe un campo activo con el nombre '{$nombre}' para este cliente.");
    }
}
