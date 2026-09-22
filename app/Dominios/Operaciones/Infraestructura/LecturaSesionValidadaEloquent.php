<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\DatosSesionValidada;
use App\Dominios\Operaciones\Contratos\LecturaSesionValidada;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajoEquipo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;

final class LecturaSesionValidadaEloquent implements LecturaSesionValidada
{
    public function obtener(int $sesionId): ?DatosSesionValidada
    {
        $sesion = Sesion::query()->with('trabajo')->find($sesionId);

        if ($sesion === null) {
            return null;
        }

        $trabajo = $sesion->trabajo;

        $condicion = ($trabajo?->orden_trabajo_id !== null && $trabajo->equipo_trabajo_id !== null)
            ? OrdenTrabajoEquipo::query()
                ->where('orden_trabajo_id', $trabajo->orden_trabajo_id)
                ->where('equipo_trabajo_id', $trabajo->equipo_trabajo_id)
                ->first()
                ?->comoCondicion()
            : null;

        return new DatosSesionValidada(
            sesionId: $sesion->id,
            trabajoId: (int) $sesion->trabajo_id,
            pilotoId: $sesion->piloto_id,
            auxiliarId: $sesion->auxiliar_id,
            hectareasDeclaradas: $sesion->hectareas_declaradas,
            fecha: $sesion->inicio->toDateString(),
            condicionPago: $condicion,
        );
    }
}
