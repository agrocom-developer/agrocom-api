<?php

namespace App\Dominios\Flota\Infraestructura;

use App\Dominios\Flota\Contratos\DatosIntegrantesEquipo;
use App\Dominios\Flota\Contratos\LecturaIntegrantesDeEquipo;
use App\Dominios\Flota\Infraestructura\Eloquent\Equipo;

/** Lectura de los integrantes de un equipo (ver {@see LecturaIntegrantesDeEquipo}). */
final class LecturaIntegrantesDeEquipoEloquent implements LecturaIntegrantesDeEquipo
{
    public function integrantes(int $equipoId): ?DatosIntegrantesEquipo
    {
        $equipo = Equipo::query()->with('dron')->find($equipoId);

        if ($equipo === null) {
            return null;
        }

        return new DatosIntegrantesEquipo(
            equipoId: $equipo->id,
            proveedorId: $equipo->proveedor_id,
            pilotoId: $equipo->piloto_id,
            dronId: $equipo->dron_id,
            dronOrigen: $equipo->dron->origen->value,
        );
    }
}
