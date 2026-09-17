<?php

namespace App\Dominios\Finanzas\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
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
            Texto::de('finanzas.errores.recurso_no_asignado_al_equipo', [
                'recurso_tipo' => $recursoTipo,
                'recurso_id' => $recursoId,
                'equipo_trabajo_id' => $equipoTrabajoId,
                'fecha' => $fecha,
            ])
        );
    }
}
