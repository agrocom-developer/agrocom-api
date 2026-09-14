<?php

namespace App\Dominios\Mantenimiento\Dominio\Excepciones;

use RuntimeException;

/**
 * Bloquea la baja a mano de `ciclos_acumulados` sin motivo explícito (HU-87,
 * tarea 102): el contador tiene que subir solo, con cada recarga real
 * (`IncrementarCiclosBateria`, disparado por `RecargaRegistrada`) — bajarlo
 * a mano SIN dejar rastro de por qué es justo lo que esta excepción cierra.
 * `ActualizarBateria::ejecutar()` la lanza cuando `$ciclosAcumulados` es
 * menor al valor actual y no llega `$motivoCorreccion`. Mismo molde que
 * `BateriaDuplicada`.
 */
final class CorreccionCiclosNoAutorizada extends RuntimeException
{
    public static function porBaja(string $identificador, int $actual, int $propuesto): self
    {
        return new self("La batería '{$identificador}' tiene {$actual} ciclos acumulados; bajar a {$propuesto} requiere un motivo de corrección.");
    }
}
