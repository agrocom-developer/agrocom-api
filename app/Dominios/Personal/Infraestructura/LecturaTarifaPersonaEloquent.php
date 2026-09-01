<?php

namespace App\Dominios\Personal\Infraestructura;

use App\Dominios\Personal\Contratos\LecturaTarifaPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;

/**
 * Implementación Eloquent del contrato de lectura de tarifa de Personal.
 * Vive fuera de `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaPersonasEloquent`: esa subcarpeta está reservada a modelos que
 * extienden `ModeloDominio` (`tests/Unit/ArquitecturaModulosTest.php` lo
 * exige), y esta clase no es un modelo — es el adaptador que el
 * `ServiceProvider` del módulo liga a {@see LecturaTarifaPersona}.
 */
final class LecturaTarifaPersonaEloquent implements LecturaTarifaPersona
{
    public function tarifaHaDe(int $personaId): ?string
    {
        return PerPersona::query()->find($personaId)?->tarifa_ha;
    }
}
