<?php

namespace App\Dominios\Mantenimiento\Infraestructura;

use App\Dominios\Mantenimiento\Contratos\DatosEquipamientoBase;
use App\Dominios\Mantenimiento\Contratos\LecturaEquipamientoPorBase;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;

/**
 * Implementación Eloquent de {@see LecturaEquipamientoPorBase}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaEquipamientoEloquent`: esa subcarpeta es solo para modelos.
 *
 * Solo cuenta filas: el soft delete de `ModeloDominio` ya deja afuera lo que
 * se dio de baja, y `base_id` es nullable en las tres tablas (un equipo puede
 * no tener base).
 */
final class LecturaEquipamientoPorBaseEloquent implements LecturaEquipamientoPorBase
{
    public function deBase(int $baseId): DatosEquipamientoBase
    {
        return new DatosEquipamientoBase(
            vehiculos: Vehiculo::query()->where('base_id', $baseId)->count(),
            generadores: Generador::query()->where('base_id', $baseId)->count(),
            baterias: Bateria::query()->where('base_id', $baseId)->count(),
        );
    }
}
