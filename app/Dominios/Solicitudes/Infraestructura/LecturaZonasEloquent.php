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
}
