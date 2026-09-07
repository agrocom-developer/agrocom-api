<?php

namespace App\Dominios\Personal\Infraestructura;

use App\Dominios\Personal\Contratos\LecturaPanelPersonal;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;

/**
 * Implementación Eloquent del contrato de nombres para el panel (tarea 67).
 * Mismo lugar y motivo que el resto de los adaptadores: no es un modelo, es
 * lo que el `ServiceProvider` liga al contrato.
 */
final class LecturaPanelPersonalEloquent implements LecturaPanelPersonal
{
    public function nombresDePersonas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return PerPersona::query()
            ->whereIn('id', $ids)
            ->pluck('nombre', 'id')
            ->all();
    }

    public function nombresDeBases(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return PerBase::query()
            ->whereIn('id', $ids)
            ->pluck('nombre', 'id')
            ->all();
    }
}
