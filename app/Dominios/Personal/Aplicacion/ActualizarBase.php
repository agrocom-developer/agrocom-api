<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;

/**
 * Edición de una base (HU-26, tarea 37).
 */
final class ActualizarBase
{
    public function ejecutar(PerBase $base, string $nombre, ?string $ubicacion, ?string $latitud, ?string $longitud): PerBase
    {
        $base->nombre = $nombre;
        $base->ubicacion = $ubicacion;
        $base->latitud = $latitud;
        $base->longitud = $longitud;
        $base->save();

        return $base->refresh();
    }
}
