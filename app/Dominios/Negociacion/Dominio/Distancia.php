<?php

namespace App\Dominios\Negociacion\Dominio;

/** Distancia entre dos puntos sobre la esfera (haversine). Pura. */
final class Distancia
{
    private const float RADIO_TIERRA_KM = 6371.0;

    /** Kilómetros, a 2 decimales. */
    public static function km(float $latitudA, float $longitudA, float $latitudB, float $longitudB): string
    {
        $dLat = deg2rad($latitudB - $latitudA);
        $dLng = deg2rad($longitudB - $longitudA);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($latitudA)) * cos(deg2rad($latitudB)) * sin($dLng / 2) ** 2;

        return number_format(self::RADIO_TIERRA_KM * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2, '.', '');
    }
}
