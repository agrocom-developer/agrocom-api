<?php

namespace App\Dominios\Personal\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `per_equipos_trabajo_codigo_unico` (tarea 72, HU-49): un código de equipo
 * no puede repetirse entre equipos ACTIVOS (uno dado de baja lógica no
 * bloquea el re-alta con el mismo código). El caso de uso que persiste
 * `EquipoTrabajo` captura la `QueryException` y la relanza como esta
 * excepción — nunca deja propagarse el 500 crudo del motor de base de datos.
 * Mismo criterio que `GeneradorDuplicado` en Mantenimiento.
 */
final class EquipoTrabajoDuplicado extends RuntimeException
{
    public static function porCodigo(string $codigo): self
    {
        return new self(Texto::de('personal.errores.equipo_trabajo_duplicado', ['codigo' => $codigo]));
    }
}
