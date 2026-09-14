<?php

namespace App\Dominios\Mantenimiento\Infraestructura;

use App\Dominios\Mantenimiento\Contratos\LecturaCiclosBateria;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;

/**
 * Implementación Eloquent del contrato de lectura de ciclos por batería.
 * Vive fuera de `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaAlertasTemperaturaBateriaEloquent`: esa subcarpeta está reservada a
 * modelos que extienden `ModeloDominio`
 * (`tests/Unit/ArquitecturaModulosTest.php` lo exige), y esta clase no es un
 * modelo — es el adaptador que el `ServiceProvider` del módulo liga a
 * {@see LecturaCiclosBateria}.
 */
final class LecturaCiclosBateriaEloquent implements LecturaCiclosBateria
{
    public function obtenerCiclosAcumulados(string $identificador): ?int
    {
        $ciclos = Bateria::query()
            ->where('identificador', $identificador)
            ->value('ciclos_acumulados');

        return $ciclos === null ? null : (int) $ciclos;
    }
}
