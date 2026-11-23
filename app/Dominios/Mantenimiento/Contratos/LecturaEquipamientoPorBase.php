<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Frontera de lectura de Mantenimiento hacia `Personal` (ADR 0003, regla 2):
 * la ficha de una base muestra, en su resumen relacionado, cuántas baterías
 * del catálogo de Mantenimiento tiene asignadas, sin importar el modelo
 * Eloquent. Hermano de {@see LecturaEquipamiento}, que resuelve lo asignable
 * a una cuadrilla.
 */
interface LecturaEquipamientoPorBase
{
    /** `$baseId` es el id de `per_bases`. Cuenta lo que no se dio de baja; sin baterías, cero. */
    public function deBase(int $baseId): DatosEquipamientoBase;
}
