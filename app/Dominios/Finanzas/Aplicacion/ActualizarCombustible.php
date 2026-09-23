<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Finanzas\Aplicacion\Concerns\VerificaCampaniaAbierta;
use App\Dominios\Finanzas\Dominio\Excepciones\RecursoNoAsignadoAlEquipo;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use Illuminate\Support\Facades\DB;

/**
 * Edición de una carga de combustible (tarea 134, queja del dueño del
 * 22/9/2026: "casi nada de Finanzas se puede corregir después de creado").
 * A diferencia de `Gasto`, `Combustible` no tiene `rendicion_id` — ninguna
 * rendición depende de su monto, así que no hay política de bloqueo por
 * fondo consumido: siempre se corrige, con las mismas dos guardas de forma
 * que `CrearCombustible` (campaña abierta vía `Concerns/VerificaCampaniaAbierta`,
 * recurso asignado al equipo en la fecha elegida).
 *
 * La fila se relee con lock (`lockForUpdate`), mismo criterio que
 * `ActualizarGasto`.
 */
final class ActualizarCombustible
{
    use VerificaCampaniaAbierta;

    public function __construct(
        private readonly LecturaCampania $lecturaCampania,
        private readonly LecturaEquipoTrabajo $lecturaEquipoTrabajo,
    ) {}

    public function ejecutar(
        Combustible $combustible,
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
        return DB::transaction(function () use (
            $combustible,
            $fecha,
            $baseId,
            $equipoTrabajoId,
            $campaniaId,
            $recursoTipo,
            $recursoId,
            $litros,
            $monto,
            $descripcion,
        ): Combustible {
            $actual = Combustible::query()->lockForUpdate()->findOrFail($combustible->id);

            $this->verificarCampaniaAbierta($this->lecturaCampania, $campaniaId);
            $this->verificarRecursoAsignado($equipoTrabajoId, $recursoTipo, $recursoId, $fecha);

            $actual->fill([
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
            $actual->save();

            return $actual->refresh();
        });
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
