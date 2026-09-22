<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;

/**
 * Edición de una persona operativa (HU-26, tarea 37). Sin ninguna regla de
 * dinero: la tarifa dejó de ser de la persona (ADR 0023).
 */
final class ActualizarPersona
{
    public function ejecutar(PerPersona $persona, DatosPersona $datos): PerPersona
    {
        $persona->fill($datos->atributos());
        $persona->save();

        return $persona->refresh();
    }
}
