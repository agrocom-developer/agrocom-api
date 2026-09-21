<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia `Personal` (ADR 0003, regla 2):
 * la ficha de una cuadrilla muestra, en su resumen relacionado, lo más
 * cercano que cuelga de ella en Operaciones — sus estadías en hacienda y los
 * trabajos que se le asignaron — sin importar los modelos `EstadiaHacienda`
 * ni `Trabajo`.
 */
interface LecturaResumenCuadrilla
{
    /** `$equipoTrabajoId` es el id de `per_equipos_trabajo`. Sin registros, todo en cero. */
    public function deCuadrilla(int $equipoTrabajoId): DatosResumenCuadrilla;
}
