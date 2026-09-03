<?php

namespace App\Dominios\Mantenimiento\Dominio\Excepciones;

use RuntimeException;

/**
 * Traducción legible de la violación del índice único parcial
 * `man_vehiculos_identificador_unico` (HU-40, tarea 50): un identificador no
 * puede repetirse entre vehículos ACTIVOS (un vehículo dado de baja lógica
 * no bloquea el re-alta con el mismo identificador). El caso de uso que
 * persiste `Vehiculo` captura la `QueryException` y la relanza como esta
 * excepción — nunca deja propagarse el 500 crudo del motor de base de datos.
 * Mismo criterio que `DronDuplicado` en Operaciones.
 */
final class VehiculoDuplicado extends RuntimeException
{
    public static function porIdentificador(string $identificador): self
    {
        return new self("Ya existe un vehículo activo con el identificador '{$identificador}'.");
    }
}
