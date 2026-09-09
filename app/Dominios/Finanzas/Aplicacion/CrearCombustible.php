<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Finanzas\Dominio\Excepciones\CampaniaCerrada;
use App\Dominios\Finanzas\Dominio\Excepciones\RecursoNoAsignadoAlEquipo;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;

/**
 * Alta de una carga de combustible (HU-35, tarea 49; reescrita por la tarea
 * 73, HU-50): "como encargado, quiero registrar el combustible del generador
 * y de los vehículos, para imputarlo a la campaña" — resuelto hasta el
 * equipo y el recurso concreto que la consumió: "así sabemos qué vehículo
 * solicitó nuevo combustible". Persiste `litros`/`monto` tal cual, sin
 * cálculo (a diferencia de `CrearGasto`, acá no hay columna de precio
 * unitario en el CA esencial).
 *
 * Dos guardas antes de guardar:
 * - `campaniaId` (ADR 0015 punto 6), si viene, no puede apuntar a una
 *   campaña `cerrada` — misma guarda que `CrearGasto`, vía
 *   `Campania\Contratos\LecturaCampania` (ADR 0003 regla 2).
 * - `recursoTipo`/`recursoId` tienen que estar ASIGNADOS al
 *   `equipoTrabajoId` elegido en la fecha de la carga — "un recurso que no
 *   le pertenecía ese día se rechaza en el caso de uso, no solo en la
 *   vista". Se verifica vía
 *   `Personal\Contratos\LecturaEquipoTrabajo::recursosAFecha()` (ADR 0003
 *   regla 2): si el recurso no aparece en esa lista, `Finanzas` no tiene
 *   forma de saber si no existe, si pertenece a otro equipo o si ya no está
 *   vigente — cualquiera de esas razones basta para rechazarlo igual.
 *
 * Inmutable salvo baja (mismo criterio que `Gasto`/`Anticipo`): sin caso de
 * uso de edición — si está mal, se da de baja (`EliminarCombustible`) y se
 * recarga.
 */
final class CrearCombustible
{
    public function __construct(
        private readonly LecturaCampania $lecturaCampania,
        private readonly LecturaEquipoTrabajo $lecturaEquipoTrabajo,
    ) {}

    public function ejecutar(
        string $fecha,
        int $baseId,
        int $equipoTrabajoId,
        ?int $campaniaId,
        string $recursoTipo,
        int $recursoId,
        string $litros,
        string $monto,
        ?string $descripcion,
    ): Combustible {
        $this->verificarCampania($campaniaId);
        $this->verificarRecursoAsignado($equipoTrabajoId, $recursoTipo, $recursoId, $fecha);

        return Combustible::query()->create([
            'fecha' => $fecha,
            'base_id' => $baseId,
            'equipo_trabajo_id' => $equipoTrabajoId,
            'campania_id' => $campaniaId,
            'recurso_tipo' => $recursoTipo,
            'recurso_id' => $recursoId,
            'litros' => $litros,
            'monto' => $monto,
            'descripcion' => $descripcion,
        ]);
    }

    /** @throws CampaniaCerrada si la campaña elegida está `cerrada`. */
    private function verificarCampania(?int $campaniaId): void
    {
        if ($campaniaId === null) {
            return;
        }

        $campania = $this->lecturaCampania->obtener($campaniaId);

        if ($campania !== null && $campania->cerrada) {
            throw CampaniaCerrada::paraCampania($campania->codigo);
        }
    }

    /** @throws RecursoNoAsignadoAlEquipo si el recurso no estaba asignado al equipo en `$fecha`. */
    private function verificarRecursoAsignado(int $equipoTrabajoId, string $recursoTipo, int $recursoId, string $fecha): void
    {
        $recursosDelEquipo = $this->lecturaEquipoTrabajo->recursosAFecha($equipoTrabajoId, $fecha);

        foreach ($recursosDelEquipo as $recurso) {
            if ($recurso->recursoTipo === $recursoTipo && $recurso->recursoId === $recursoId) {
                return;
            }
        }

        throw RecursoNoAsignadoAlEquipo::paraRecurso($recursoTipo, $recursoId, $equipoTrabajoId, $fecha);
    }
}
