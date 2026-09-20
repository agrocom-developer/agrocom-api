<?php

namespace App\Dominios\Personal\Infraestructura;

use App\Dominios\Personal\Contratos\DatosCuadrillasDeRecurso;
use App\Dominios\Personal\Contratos\LecturaCuadrillasPorRecurso;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación Eloquent de {@see LecturaCuadrillasPorRecurso}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaEquipoTrabajoEloquent`: esa subcarpeta es solo para modelos.
 *
 * Una cuadrilla cuenta una sola vez aunque el recurso se le haya asignado en
 * dos tramos (`distinct` sobre `equipo_trabajo_id`). `whereHas('equipoTrabajo')`
 * respeta el soft delete de la cuadrilla: la que se dio de baja no aparece.
 */
final class LecturaCuadrillasPorRecursoEloquent implements LecturaCuadrillasPorRecurso
{
    public function deRecurso(string $recursoTipo, int $recursoId, string $fecha): DatosCuadrillasDeRecurso
    {
        $vigentes = $this->asignacionesDe($recursoTipo, $recursoId)
            ->where('desde', '<=', $fecha)
            ->where(fn (Builder $consulta) => $consulta->whereNull('hasta')->orWhere('hasta', '>=', $fecha))
            ->with('equipoTrabajo')
            ->get()
            ->unique('equipo_trabajo_id');

        return new DatosCuadrillasDeRecurso(
            vigentes: $vigentes->count(),
            historial: $this->asignacionesDe($recursoTipo, $recursoId)->distinct()->count('equipo_trabajo_id'),
            codigosVigentes: $vigentes
                ->map(fn (EquipoRecurso $asignacion): string => $asignacion->equipoTrabajo->codigo)
                ->sort()
                ->values()
                ->all(),
        );
    }

    /** @return Builder<EquipoRecurso> */
    private function asignacionesDe(string $recursoTipo, int $recursoId): Builder
    {
        return EquipoRecurso::query()
            ->where('recurso_tipo', $recursoTipo)
            ->where('recurso_id', $recursoId)
            ->whereHas('equipoTrabajo');
    }
}
