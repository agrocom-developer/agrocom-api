<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Municipio;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;

/**
 * Caso de uso: el municipio de la propiedad con su provincia y departamento,
 * para que el editor de mapa arranque en él (19/9/2026, pedido directo: con el
 * municipio guardado, el mapa se ubica ahí y no en Santa Cruz de la Sierra, y
 * el perímetro se dibuja sin recorrer medio país).
 *
 * `com_municipios` no guarda coordenadas (ver
 * {@see ResolverCentroReferenciaPropiedad}), así que el servidor solo entrega
 * los NOMBRES y es el editor quien los convierte en un punto con un
 * geocodificador (Nominatim en Leaflet, Geocoder en Google Maps). Provincia y
 * departamento acompañan al municipio porque el nombre solo no alcanza: hay
 * municipios que se repiten entre provincias distintas.
 */
final class ResolverMunicipioPropiedad
{
    /** @return array{municipio: string, provincia: string, departamento: string}|null `null` si la propiedad no tiene municipio cargado. */
    public function ejecutar(Propiedad $propiedad): ?array
    {
        if ($propiedad->municipio_id === null) {
            return null;
        }

        $municipio = Municipio::query()->with('provincia.departamento')->find($propiedad->municipio_id);

        if ($municipio === null) {
            return null;
        }

        return [
            'municipio' => $municipio->nombre,
            'provincia' => $municipio->provincia->nombre,
            'departamento' => $municipio->provincia->departamento->nombre,
        ];
    }
}
