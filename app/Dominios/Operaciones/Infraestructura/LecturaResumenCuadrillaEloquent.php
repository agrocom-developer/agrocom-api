<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\DatosResumenCuadrilla;
use App\Dominios\Operaciones\Contratos\LecturaResumenCuadrilla;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;

/**
 * Implementación Eloquent de {@see LecturaResumenCuadrilla}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaOrdenesVigentesEloquent`: esa subcarpeta es solo para modelos.
 *
 * "Trabajo abierto" es `EstadoTrabajo::Abierto` (el único estado que no es
 * terminal — ver docblock de ese enum); "estadía en curso" es `salida IS
 * NULL`, mismo criterio que `EstadiaHacienda::estado()`.
 */
final class LecturaResumenCuadrillaEloquent implements LecturaResumenCuadrilla
{
    public function deCuadrilla(int $equipoTrabajoId): DatosResumenCuadrilla
    {
        return new DatosResumenCuadrilla(
            estadiasTotal: EstadiaHacienda::query()->where('equipo_trabajo_id', $equipoTrabajoId)->count(),
            estadiasEnCurso: EstadiaHacienda::query()->where('equipo_trabajo_id', $equipoTrabajoId)->whereNull('salida')->count(),
            trabajosTotal: Trabajo::query()->where('equipo_trabajo_id', $equipoTrabajoId)->count(),
            trabajosAbiertos: Trabajo::query()->where('equipo_trabajo_id', $equipoTrabajoId)->where('estado', EstadoTrabajo::Abierto)->count(),
        );
    }
}
