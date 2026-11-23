<?php

namespace App\Dominios\Mantenimiento\Infraestructura;

use App\Dominios\Mantenimiento\Contratos\DatosEquipamientoBase;
use App\Dominios\Mantenimiento\Contratos\LecturaEquipamientoPorBase;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;

/**
 * Implementación Eloquent de {@see LecturaEquipamientoPorBase}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaEquipamientoEloquent`: esa subcarpeta es solo para modelos.
 *
 * Solo cuenta filas: el soft delete de `ModeloDominio` ya deja afuera lo que
 * se dio de baja, y `base_id` es nullable (una batería puede no tener base).
 */
final class LecturaEquipamientoPorBaseEloquent implements LecturaEquipamientoPorBase
{
    public function deBase(int $baseId): DatosEquipamientoBase
    {
        return new DatosEquipamientoBase(
            baterias: Bateria::query()->where('base_id', $baseId)->count(),
        );
    }
}
