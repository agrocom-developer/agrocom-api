<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Frontera de lectura de Mantenimiento hacia `Personal` (ADR 0003, regla 2):
 * la ficha de una base muestra, en su resumen relacionado, cuánto
 * equipamiento del catálogo de Mantenimiento tiene asignado —vehículos,
 * generadores y baterías— sin importar sus modelos Eloquent. Hermano de
 * {@see LecturaEquipamiento}, que resuelve lo asignable a una cuadrilla.
 */
interface LecturaEquipamientoPorBase
{
    /** `$baseId` es el id de `per_bases`. Cuenta lo que no se dio de baja; sin equipamiento, todo en cero. */
    public function deBase(int $baseId): DatosEquipamientoBase;
}
