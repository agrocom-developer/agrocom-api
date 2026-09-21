<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `com_cultivos_nombre_comun_unico` (HU-48, tarea 71): un nombre de cultivo no
 * puede repetirse entre cultivos ACTIVOS (no borrados). El caso de uso que
 * persiste `Cultivo` captura la `QueryException` y la relanza como esta
 * excepción — nunca deja propagarse el 500 crudo del motor de base de datos.
 * Mismo criterio que `LoteDuplicado`/`ClienteDuplicado`.
 */
final class CultivoDuplicado extends RuntimeException
{
    public static function porNombre(string $nombre): self
    {
        return new self(Texto::de('comercial.errores.cultivo_nombre_duplicado', ['nombre' => $nombre]));
    }
}
