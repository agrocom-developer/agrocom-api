<?php

namespace App\Dominios\Campania\Infraestructura;

use App\Dominios\Campania\Contratos\DatosCampania;
use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;

/**
 * Implementación Eloquent del contrato de lectura de campaña. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaSesionValidadaEloquent`: esa subcarpeta está reservada a modelos
 * que extienden `ModeloDominio` (`tests/Unit/ArquitecturaModulosTest.php` lo
 * exige), y esta clase no es un modelo — es el adaptador que el
 * `ServiceProvider` del módulo liga a {@see LecturaCampania}.
 */
final class LecturaCampaniaEloquent implements LecturaCampania
{
    public function obtener(int $campaniaId): ?DatosCampania
    {
        $campania = Campania::query()->find($campaniaId);

        if ($campania === null) {
            return null;
        }

        return new DatosCampania(
            id: $campania->id,
            codigo: $campania->codigo,
            cerrada: $campania->estado === EstadoCampania::Cerrada,
        );
    }
}
