<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\PlanMantenimiento;

/**
 * Alta de un plan de mantenimiento preventivo (HU-38, tarea 54): modelo de
 * dron, tarea preventiva y umbral de horas de vuelo. Sin índice único sobre
 * `modelo` (ver docblock de la migración): nada impide dos planes para el
 * mismo modelo con umbrales distintos.
 */
final class CrearPlanMantenimiento
{
    public function ejecutar(string $modelo, string $tarea, string $horasUmbral): PlanMantenimiento
    {
        $plan = new PlanMantenimiento([
            'modelo' => $modelo,
            'tarea' => $tarea,
            'horas_umbral' => $horasUmbral,
        ]);

        $plan->save();

        return $plan->refresh();
    }
}
