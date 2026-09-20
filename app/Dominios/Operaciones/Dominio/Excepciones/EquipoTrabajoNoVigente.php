<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Guarda de `AsignarEquiposOrden` (HU-70, tarea 85): un equipo solo puede
 * recibir trabajo si está vigente HOY (`Personal\Contratos\LecturaEquipoTrabajo::vigentesAFecha()`)
 * — un equipo dado de baja o que todavía no arrancó su vigencia no es una
 * cuadrilla real a la que repartirle hectáreas.
 *
 * Reutilizada por `Aplicacion/RegistrarEstadiaHacienda` (reforma 19/9/2026):
 * misma guarda, aplicada a la fecha de ENTRADA de la estadía en vez de hoy —
 * una cuadrilla que todavía no existía o ya se dio de baja ese día no pudo
 * alojarse en ninguna hacienda.
 */
final class EquipoTrabajoNoVigente extends RuntimeException
{
    public static function porId(int $equipoTrabajoId): self
    {
        return new self(Texto::de('operaciones.errores.equipo_trabajo_no_vigente', ['id' => $equipoTrabajoId]));
    }
}
