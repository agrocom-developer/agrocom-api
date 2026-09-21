<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;

/**
 * Alta de una persona de campo (HU-26, tarea 37; ampliada el 21/9/2026 con
 * datos personales y de referencia — ver `DatosPersona`). `nombre` se compone
 * acá, nunca lo escribe el formulario. La unicidad del `ci` entre personas
 * vivas la validan el request y el índice parcial de la tabla.
 */
final class CrearPersona
{
    public function ejecutar(DatosPersona $datos): PerPersona
    {
        $persona = new PerPersona($datos->atributos());
        $persona->save();

        return $persona->refresh();
    }
}
