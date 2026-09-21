<?php

namespace App\Dominios\Personal\Infraestructura;

use App\Dominios\Personal\Contratos\DatosFichaPersona;
use App\Dominios\Personal\Contratos\LecturaFichaPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;

/**
 * Implementación Eloquent del contrato de ficha de persona. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaTarifaPersonaEloquent`: esa subcarpeta es de modelos.
 */
final class LecturaFichaPersonaEloquent implements LecturaFichaPersona
{
    public function dePersona(int $personaId): ?DatosFichaPersona
    {
        $persona = PerPersona::query()->with('base')->find($personaId);

        if ($persona === null) {
            return null;
        }

        return new DatosFichaPersona(
            id: (int) $persona->id,
            nombre: $persona->nombre,
            rol: $persona->rol->value,
            baseNombre: $persona->base?->nombre,
        );
    }
}
