<?php

namespace App\Dominios\Solicitudes\Infraestructura;

use App\Dominios\Solicitudes\Contratos\LecturaZonas;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;

/** Lectura de zonas para otros módulos (ver {@see LecturaZonas}). */
final class LecturaZonasEloquent implements LecturaZonas
{
    public function nombre(int $id): ?string
    {
        return Zona::query()->whereKey($id)->value('nombre');
    }

    /** {@inheritDoc} */
    public function todas(): array
    {
        return Zona::query()->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    /** {@inheritDoc} */
    public function centroide(int $id): ?array
    {
        $zona = Zona::query()->find($id);

        return $zona === null ? null : ['lat' => (float) $zona->centroide_lat, 'lng' => (float) $zona->centroide_lng];
    }
}
