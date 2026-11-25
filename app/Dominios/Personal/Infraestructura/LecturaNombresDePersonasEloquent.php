<?php

namespace App\Dominios\Personal\Infraestructura;

use App\Dominios\Personal\Contratos\LecturaNombresDePersonas;
use Illuminate\Support\Facades\DB;

/**
 * Implementación del contrato de nombres de persona. Vive fuera de
 * `Infraestructura/Eloquent/` por el mismo criterio que
 * `LecturaFichaPersonaEloquent`.
 */
final class LecturaNombresDePersonasEloquent implements LecturaNombresDePersonas
{
    public function vivasExcepto(iterable $idsExcluidos): array
    {
        return DB::table('per_personas')
            ->whereNull('deleted_at')
            ->whereNotIn('id', $idsExcluidos)
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->all();
    }

    public function nombresDe(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('per_personas')
            ->whereIn('id', $ids)
            ->pluck('nombre', 'id')
            ->all();
    }
}
