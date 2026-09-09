<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;

/**
 * Finaliza la vigencia de un recurso asignado (tarea 72, HU-49): fija
 * `hasta`, nunca borra la fila — mismo criterio que
 * `DesasignarIntegranteEquipo`.
 */
final class DesasignarRecursoEquipo
{
    public function ejecutar(EquipoRecurso $recurso, string $hasta): EquipoRecurso
    {
        $recurso->fill(['hasta' => $hasta]);
        $recurso->save();

        return $recurso->refresh();
    }
}
