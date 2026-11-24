<?php

namespace App\Dominios\Asignacion\Dominio;

/**
 * Geometría mínima de la asignación: la distancia entre dos puntos y el
 * centro de un polígono GeoJSON. La distancia no es dinero ni hectáreas, así
 * que va en punto flotante y se redondea a dos decimales al guardarla.
 */
final class Geografia
{
    private const float RADIO_TIERRA_KM = 6371.0088;

    /** Distancia en kilómetros sobre la esfera (fórmula de haversine). */
    public static function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round(2 * self::RADIO_TIERRA_KM * asin(min(1.0, sqrt($a))), 2);
    }

    /**
     * Centro (promedio de los vértices del anillo exterior) de un `Polygon` o
     * `MultiPolygon` GeoJSON, o `null` si la geometría no sirve.
     *
     * @param  array<string, mixed>|null  $geometria
     * @return array{lat: float, lng: float}|null
     */
    public static function centroDe(?array $geometria): ?array
    {
        if ($geometria === null) {
            return null;
        }

        $anillo = match ($geometria['type'] ?? null) {
            'Polygon' => $geometria['coordinates'][0] ?? null,
            'MultiPolygon' => $geometria['coordinates'][0][0] ?? null,
            default => null,
        };

        if (! is_array($anillo) || $anillo === []) {
            return null;
        }

        $lat = 0.0;
        $lng = 0.0;
        $n = 0;

        foreach ($anillo as $punto) {
            if (! is_array($punto) || ! isset($punto[0], $punto[1]) || ! is_numeric($punto[0]) || ! is_numeric($punto[1])) {
                continue;
            }

            $lng += (float) $punto[0];
            $lat += (float) $punto[1];
            $n++;
        }

        return $n === 0 ? null : ['lat' => $lat / $n, 'lng' => $lng / $n];
    }
}
