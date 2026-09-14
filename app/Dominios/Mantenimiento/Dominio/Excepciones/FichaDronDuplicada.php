<?php

namespace App\Dominios\Mantenimiento\Dominio\Excepciones;

use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `man_drones_identificador_dron_unico` (HU-82, tarea 97): un
 * `identificador_dron` no puede repetirse entre fichas ACTIVAS (una ficha
 * dada de baja lógica no bloquea el re-alta con el mismo identificador). El
 * caso de uso que persiste `FichaDron` captura la `QueryException` y la
 * relanza como esta excepción — nunca deja propagarse el 500 crudo del motor
 * de base de datos. Mismo criterio que `BateriaDuplicada` en este mismo
 * módulo.
 */
final class FichaDronDuplicada extends RuntimeException
{
    public static function porIdentificador(string $identificadorDron): self
    {
        return new self("Ya existe una ficha activa para el dron con identificador '{$identificadorDron}'.");
    }
}
