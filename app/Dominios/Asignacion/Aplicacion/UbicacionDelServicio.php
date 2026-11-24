<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Dominio\Geografia;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudParaAsignar;

/**
 * Dónde está el servicio: el centro de la geometría del terreno y, si el
 * terreno no la trae, el centro de su zona. Sin ninguna de las dos no hay
 * distancia que medir.
 */
final class UbicacionDelServicio
{
    /** @return array{lat: float, lng: float}|null */
    public static function de(DatosSolicitudParaAsignar $solicitud): ?array
    {
        $centro = Geografia::centroDe($solicitud->geometriaTerreno);

        if ($centro !== null) {
            return $centro;
        }

        return $solicitud->zonaLat !== null && $solicitud->zonaLng !== null
            ? ['lat' => (float) $solicitud->zonaLat, 'lng' => (float) $solicitud->zonaLng]
            : null;
    }
}
