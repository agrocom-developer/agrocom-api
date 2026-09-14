<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;

/**
 * Alta de una base (HU-26, tarea 37): catálogo simple de nombre y ubicación,
 * sin restricción de unicidad (la migración no la declara).
 */
final class CrearBase
{
    public function ejecutar(string $nombre, ?string $ubicacion, ?string $latitud, ?string $longitud): PerBase
    {
        $base = new PerBase([
            'nombre' => $nombre,
            'ubicacion' => $ubicacion,
            'latitud' => $latitud,
            'longitud' => $longitud,
        ]);

        $base->save();

        return $base->refresh();
    }
}
