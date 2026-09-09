<?php

namespace App\Dominios\Mantenimiento\Dominio\Excepciones;

use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `man_baterias_identificador_unico` (HU-39, tarea 51): un identificador no
 * puede repetirse entre baterías ACTIVAS (una batería dada de baja lógica
 * no bloquea el re-alta con el mismo identificador). El caso de uso que
 * persiste `Bateria` captura la `QueryException` y la relanza como esta
 * excepción — nunca deja propagarse el 500 crudo del motor de base de
 * datos. Mismo criterio que `VehiculoDuplicado` en este mismo módulo.
 */
final class BateriaDuplicada extends RuntimeException
{
    public static function porIdentificador(string $identificador): self
    {
        return new self("Ya existe una batería activa con el identificador '{$identificador}'.");
    }
}
