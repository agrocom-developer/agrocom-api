<?php

namespace App\Dominios\Personal\Infraestructura;

use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\DatosIntegranteEquipo;
use App\Dominios\Personal\Contratos\DatosRecursoEquipo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoIntegrante;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;

/**
 * Implementación Eloquent del contrato de lectura de equipos de trabajo.
 * Vive fuera de `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaCampaniaEloquent`: esa subcarpeta está reservada a modelos que
 * extienden `ModeloDominio`, y esta clase es el adaptador que el
 * `ServiceProvider` liga a {@see LecturaEquipoTrabajo}.
 */
final class LecturaEquipoTrabajoEloquent implements LecturaEquipoTrabajo
{
    public function vigentesAFecha(string $fecha): array
    {
        return EquipoTrabajo::query()
            ->where('desde', '<=', $fecha)
            ->where(fn ($consulta) => $consulta->whereNull('hasta')->orWhere('hasta', '>=', $fecha))
            ->orderBy('codigo')
            ->get()
            ->map(fn (EquipoTrabajo $equipo): DatosEquipoTrabajo => new DatosEquipoTrabajo(
                id: $equipo->id,
                codigo: $equipo->codigo,
                nombre: $equipo->nombre,
                baseId: $equipo->base_id,
                estado: $equipo->estado->value,
                desde: $equipo->desde->toDateString(),
                hasta: $equipo->hasta?->toDateString(),
            ))
            ->all();
    }

    public function porIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return EquipoTrabajo::query()
            ->withTrashed()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (EquipoTrabajo $equipo): array => [$equipo->id => new DatosEquipoTrabajo(
                id: $equipo->id,
                codigo: $equipo->codigo,
                nombre: $equipo->nombre,
                baseId: $equipo->base_id,
                estado: $equipo->estado->value,
                desde: $equipo->desde->toDateString(),
                hasta: $equipo->hasta?->toDateString(),
            )])
            ->all();
    }

    public function integrantesAFecha(int $equipoTrabajoId, string $fecha): array
    {
        return EquipoIntegrante::query()
            ->where('equipo_trabajo_id', $equipoTrabajoId)
            ->where('desde', '<=', $fecha)
            ->where(fn ($consulta) => $consulta->whereNull('hasta')->orWhere('hasta', '>=', $fecha))
            ->with('persona')
            ->orderBy('desde')
            ->get()
            ->map(fn (EquipoIntegrante $integrante): DatosIntegranteEquipo => new DatosIntegranteEquipo(
                id: $integrante->id,
                personaId: $integrante->persona_id,
                nombrePersona: $integrante->persona->nombre,
                rolEquipo: $integrante->rol_equipo->value,
                desde: $integrante->desde->toDateString(),
                hasta: $integrante->hasta?->toDateString(),
            ))
            ->all();
    }

    public function recursosAFecha(int $equipoTrabajoId, string $fecha): array
    {
        return EquipoRecurso::query()
            ->where('equipo_trabajo_id', $equipoTrabajoId)
            ->where('desde', '<=', $fecha)
            ->where(fn ($consulta) => $consulta->whereNull('hasta')->orWhere('hasta', '>=', $fecha))
            ->orderBy('desde')
            ->get()
            ->map(fn (EquipoRecurso $recurso): DatosRecursoEquipo => new DatosRecursoEquipo(
                id: $recurso->id,
                recursoTipo: $recurso->recurso_tipo->value,
                recursoId: $recurso->recurso_id,
                desde: $recurso->desde->toDateString(),
                hasta: $recurso->hasta?->toDateString(),
            ))
            ->all();
    }
}
