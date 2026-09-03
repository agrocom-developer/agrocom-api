<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\PlanMantenimiento;
use Illuminate\Support\Facades\Auth;

/**
 * Baja lógica de un plan de mantenimiento preventivo (HU-38, tarea 54).
 * Soft delete, nunca físico (ADR 0007) — la fila queda para auditoría con
 * quién la dio de baja.
 *
 * `updated_by` se fija a mano ANTES del `delete()`: el soft delete de
 * Eloquent hace un `UPDATE` por query builder que no dispara el evento
 * `updating` (el que completa `updated_by` vía `RegistraAutoria`), mismo
 * criterio que `EliminarBateria`/`EliminarVehiculo`.
 */
final class EliminarPlanMantenimiento
{
    public function ejecutar(PlanMantenimiento $plan): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $plan->updated_by = (int) $usuarioId;
            $plan->save();
        }

        $plan->delete();
    }
}
