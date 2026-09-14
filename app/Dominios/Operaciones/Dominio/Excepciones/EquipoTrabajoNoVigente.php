<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use RuntimeException;

/**
 * Guarda de `AsignarEquiposOrden` (HU-70, tarea 85): un equipo solo puede
 * recibir trabajo si está vigente HOY (`Personal\Contratos\LecturaEquipoTrabajo::vigentesAFecha()`)
 * — un equipo dado de baja o que todavía no arrancó su vigencia no es una
 * cuadrilla real a la que repartirle hectáreas.
 */
final class EquipoTrabajoNoVigente extends RuntimeException
{
    public static function porId(int $equipoTrabajoId): self
    {
        return new self("El equipo de trabajo #{$equipoTrabajoId} no está vigente hoy.");
    }
}
