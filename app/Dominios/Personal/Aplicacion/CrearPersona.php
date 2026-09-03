<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;

/**
 * Alta de una persona operativa (HU-26, tarea 37): nombre, rol, base
 * opcional y tarifa por hectárea. Sin restricción de unicidad (la migración
 * no la declara).
 */
final class CrearPersona
{
    public function ejecutar(
        string $nombre,
        RolOperativoPersona $rol,
        ?int $baseId,
        ?string $tarifaHa,
        bool $activo,
    ): PerPersona {
        $persona = new PerPersona([
            'nombre' => $nombre,
            'rol' => $rol,
            'base_id' => $baseId,
            'tarifa_ha' => $tarifaHa,
            'activo' => $activo,
        ]);

        $persona->save();

        return $persona->refresh();
    }
}
