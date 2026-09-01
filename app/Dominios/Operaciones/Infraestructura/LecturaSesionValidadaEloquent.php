<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\DatosSesionValidada;
use App\Dominios\Operaciones\Contratos\LecturaSesionValidada;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;

/**
 * Implementación Eloquent del contrato de lectura de sesión validada. Vive
 * fuera de `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaOrdenesVigentesEloquent`: esa subcarpeta está reservada a modelos
 * que extienden `ModeloDominio` (`tests/Unit/ArquitecturaModulosTest.php` lo
 * exige), y esta clase no es un modelo — es el adaptador que el
 * `ServiceProvider` del módulo liga a {@see LecturaSesionValidada}.
 */
final class LecturaSesionValidadaEloquent implements LecturaSesionValidada
{
    public function obtener(int $sesionId): ?DatosSesionValidada
    {
        $sesion = Sesion::query()->find($sesionId);

        if ($sesion === null) {
            return null;
        }

        return new DatosSesionValidada(
            sesionId: $sesion->id,
            pilotoId: $sesion->piloto_id,
            auxiliarId: $sesion->auxiliar_id,
            hectareasDeclaradas: $sesion->hectareas_declaradas,
        );
    }
}
