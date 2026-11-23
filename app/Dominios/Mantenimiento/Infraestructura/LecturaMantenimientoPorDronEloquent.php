<?php

namespace App\Dominios\Mantenimiento\Infraestructura;

use App\Dominios\Mantenimiento\Contratos\DatosMantenimientoDron;
use App\Dominios\Mantenimiento\Contratos\LecturaMantenimientoPorDron;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\FichaDron;

/**
 * Implementación Eloquent de {@see LecturaMantenimientoPorDron}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaEquipamientoPorBaseEloquent`: esa subcarpeta es solo para modelos.
 *
 * El soft delete de `ModeloDominio` ya deja afuera lo que se dio de baja. El
 * índice único parcial de `man_drones` garantiza a lo sumo una ficha viva por
 * identificador.
 */
final class LecturaMantenimientoPorDronEloquent implements LecturaMantenimientoPorDron
{
    public function deDron(string $identificador): DatosMantenimientoDron
    {
        $ficha = FichaDron::query()->where('identificador_dron', $identificador)->first();

        return new DatosMantenimientoDron(
            fichaId: $ficha?->id,
            numeroSerie: $ficha?->numero_serie,
            versionSoftware: $ficha?->version_software,
        );
    }
}
