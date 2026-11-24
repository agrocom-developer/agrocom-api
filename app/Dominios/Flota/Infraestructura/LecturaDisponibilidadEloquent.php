<?php

namespace App\Dominios\Flota\Infraestructura;

use App\Dominios\Flota\Contratos\DatosDisponibilidad;
use App\Dominios\Flota\Contratos\LecturaDisponibilidad;
use App\Dominios\Flota\Infraestructura\Eloquent\Disponibilidad;

/** Lectura de la disponibilidad declarada, para la asignación (ver {@see LecturaDisponibilidad}). */
final class LecturaDisponibilidadEloquent implements LecturaDisponibilidad
{
    public function porEquipos(array $equipoIds): array
    {
        if ($equipoIds === []) {
            return [];
        }

        $resultado = [];

        foreach (Disponibilidad::query()->whereIn('equipo_id', $equipoIds)->get() as $fila) {
            $resultado[$fila->equipo_id] = new DatosDisponibilidad(
                $fila->equipo_id,
                $fila->en_linea,
                $fila->ubicacion_lat === null ? null : (string) $fila->ubicacion_lat,
                $fila->ubicacion_lng === null ? null : (string) $fila->ubicacion_lng,
                $fila->actualizado_en->toIso8601String(),
            );
        }

        return $resultado;
    }
}
