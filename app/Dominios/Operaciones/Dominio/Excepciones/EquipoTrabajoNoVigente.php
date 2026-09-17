<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
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
        return new self(Texto::de('operaciones.errores.equipo_trabajo_no_vigente', ['id' => $equipoTrabajoId]));
    }
}
