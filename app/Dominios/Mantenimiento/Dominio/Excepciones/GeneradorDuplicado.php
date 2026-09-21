<?php

namespace App\Dominios\Mantenimiento\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `man_generadores_identificador_unico` (tarea 72, HU-49): un identificador
 * no puede repetirse entre generadores ACTIVOS (uno dado de baja lógica no
 * bloquea el re-alta con el mismo identificador). El caso de uso que
 * persiste `Generador` captura la `QueryException` y la relanza como esta
 * excepción — nunca deja propagarse el 500 crudo del motor de base de datos.
 * Mismo criterio que `VehiculoDuplicado`.
 */
final class GeneradorDuplicado extends RuntimeException
{
    public static function porIdentificador(string $identificador): self
    {
        return new self(Texto::de('mantenimiento.errores.generador_duplicado', ['identificador' => $identificador]));
    }
}
