<?php

namespace App\Dominios\Personal\Infraestructura;

use App\Dominios\Personal\Contratos\DatosPersonales;
use App\Dominios\Personal\Contratos\LecturaDatosPersonales;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;

/**
 * Implementación Eloquent de {@see LecturaDatosPersonales}. Una persona por
 * id, sin listados ni filtros: no hay forma de recorrer a las demás.
 */
final class LecturaDatosPersonalesEloquent implements LecturaDatosPersonales
{
    public function dePersona(int $personaId): ?DatosPersonales
    {
        $persona = PerPersona::query()->with('base')->find($personaId);

        if ($persona === null) {
            return null;
        }

        return new DatosPersonales(
            id: (int) $persona->id,
            nombre: $persona->nombre,
            rol: $persona->rol->value,
            baseNombre: $persona->base?->nombre,
            ci: $this->textoONull($persona->ci),
            celular: $this->textoONull($persona->celular),
            correo: $this->textoONull($persona->correo),
            direccion: $this->textoONull($persona->direccion),
        );
    }

    private function textoONull(mixed $valor): ?string
    {
        $texto = is_string($valor) ? trim($valor) : '';

        return $texto === '' ? null : $texto;
    }
}
