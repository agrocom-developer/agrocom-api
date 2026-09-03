<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\PlanMantenimiento;

/**
 * Edición de un plan de mantenimiento preventivo (HU-38, tarea 54). Mismas
 * reglas que `CrearPlanMantenimiento` — ver ese docblock.
 */
final class ActualizarPlanMantenimiento
{
    public function ejecutar(PlanMantenimiento $plan, string $modelo, string $tarea, string $horasUmbral): PlanMantenimiento
    {
        $plan->modelo = $modelo;
        $plan->tarea = $tarea;
        $plan->horas_umbral = $horasUmbral;

        $plan->save();

        return $plan->refresh();
    }
}
