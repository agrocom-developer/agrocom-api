<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `com_propiedades_nombre_unico` (ADR 0018): un nombre de propiedad no puede
 * repetirse entre propiedades ACTIVAS del mismo cliente. El caso de uso que
 * persiste `Propiedad` captura la `QueryException` y la relanza como esta
 * excepción — nunca deja propagarse el 500 crudo del motor de base de datos.
 * Mismo criterio que `CampoDuplicado`/`ClienteDuplicado`.
 */
final class PropiedadDuplicada extends RuntimeException
{
    public static function porNombre(string $nombre): self
    {
        return new self("Ya existe una propiedad activa con el nombre '{$nombre}' para este cliente.");
    }
}
