<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use RuntimeException;

/**
 * Guarda central de la tarea 73 (HU-50): un recurso (dron/vehículo/
 * generador) solo puede imputarse a un equipo de trabajo si ESE equipo lo
 * tenía asignado en la fecha de la carga — "es la regla que hace confiable
 * la imputación", no una comodidad de la vista. La verifica
 * `Aplicacion/CrearCombustible` vía
 * `Personal\Contratos\LecturaEquipoTrabajo::recursosAFecha()` (ADR 0003
 * regla 2: la vigencia del recurso es dato de `Personal`, esta excepción es
 * la reacción de `Finanzas` ante una lista que no lo contiene) — mismo
 * criterio que su guarda hermana {@see CampaniaCerrada}.
 */
final class RecursoNoAsignadoAlEquipo extends RuntimeException
{
    public static function paraRecurso(string $recursoTipo, int $recursoId, int $equipoTrabajoId, string $fecha): self
    {
        return new self(
            "El recurso '{$recursoTipo}' #{$recursoId} no estaba asignado al equipo #{$equipoTrabajoId} el {$fecha}."
        );
    }
}
