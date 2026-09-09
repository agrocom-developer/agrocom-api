<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `ope_drones_identificador_unico` (HU-27, tarea 36): un identificador no
 * puede repetirse entre drones ACTIVOS (un dron dado de baja lógica no
 * bloquea el re-alta con el mismo identificador). El caso de uso que
 * persiste `Dron` captura la `QueryException` y la relanza como esta
 * excepción — nunca deja propagarse el 500 crudo del motor de base de datos.
 * Mismo criterio que `ClienteDuplicado` en Comercial.
 */
final class DronDuplicado extends RuntimeException
{
    public static function porIdentificador(string $identificador): self
    {
        return new self("Ya existe un dron activo con el identificador '{$identificador}'.");
    }
}
