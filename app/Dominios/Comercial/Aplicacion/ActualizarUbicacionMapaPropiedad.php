<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;

/**
 * Guarda el punto de referencia (latitud/longitud) y el perímetro
 * (`geometria`, GeoJSON `MultiPolygon`) de una propiedad — pantalla aparte
 * de mapa (`/panel/propiedades/{propiedad}/mapa`), tal como ADR 0020 ya
 * había dejado señalado ("el editor de mapa multi-polígono se construye en
 * un feature aparte"). No toca ningún otro dato de la propiedad — mismo
 * criterio que `GuardarSiembraCampania`, que tampoco toca datos principales.
 */
final class ActualizarUbicacionMapaPropiedad
{
    /** @param  array<string, mixed>|null  $geometria */
    public function ejecutar(
        Propiedad $propiedad,
        ?string $latitud,
        ?string $longitud,
        ?array $geometria,
    ): Propiedad {
        $propiedad->latitud = $latitud;
        $propiedad->longitud = $longitud;
        $propiedad->geometria = $geometria;
        $propiedad->save();

        return $propiedad->refresh();
    }
}
