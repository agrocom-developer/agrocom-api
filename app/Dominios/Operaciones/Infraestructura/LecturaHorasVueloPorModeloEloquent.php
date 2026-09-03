<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\LecturaHorasVueloPorModelo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;

/**
 * Implementación Eloquent del contrato de lectura de horas de vuelo por
 * modelo de dron. Vive fuera de `Infraestructura/Eloquent/` a propósito,
 * mismo criterio que `LecturaAlertasTemperaturaBateriaEloquent`: esa
 * subcarpeta está reservada a modelos que extienden `ModeloDominio`
 * (`tests/Unit/ArquitecturaModulosTest.php` lo exige), y esta clase no es un
 * modelo — es el adaptador que el `ServiceProvider` del módulo liga a
 * {@see LecturaHorasVueloPorModelo}.
 *
 * La suma se hace en PHP con Carbon (`diffInSeconds`), nunca con aritmética
 * de fechas en SQL crudo: Postgres y SQLite (motor de los tests) difieren en
 * sus funciones de fecha.
 */
final class LecturaHorasVueloPorModeloEloquent implements LecturaHorasVueloPorModelo
{
    public function horasAcumuladasPorModelo(string $modelo): array
    {
        $dronIds = Dron::query()->where('modelo', $modelo)->pluck('id');

        if ($dronIds->isEmpty()) {
            return [];
        }

        $horasPorDron = [];

        Sesion::query()
            ->whereIn('dron_id', $dronIds)
            ->whereNotNull('fin')
            ->get(['dron_id', 'inicio', 'fin'])
            ->each(function (Sesion $sesion) use (&$horasPorDron): void {
                $dronId = (int) $sesion->dron_id;
                // abs(): esta versión de Carbon devuelve diffInSeconds() con
                // signo (negativo cuando el receptor es posterior al
                // argumento), y una sesión cerrada siempre tiene fin > inicio.
                $horas = abs($sesion->fin->diffInSeconds($sesion->inicio)) / 3600;

                $horasPorDron[$dronId] = ($horasPorDron[$dronId] ?? 0.0) + $horas;
            });

        return $horasPorDron;
    }
}
