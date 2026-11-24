<?php

namespace App\Dominios\Flota\Infraestructura;

use App\Dominios\Flota\Contratos\DatosDron;
use App\Dominios\Flota\Contratos\LecturaDrones;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;

/** Lectura de drones para otros módulos (ver {@see LecturaDrones}). */
final class LecturaDronesEloquent implements LecturaDrones
{
    public function dron(int $id): ?DatosDron
    {
        $dron = Dron::query()->find($id);

        return $dron === null ? null : new DatosDron(
            $dron->id,
            $dron->dueno_proveedor_id,
            $dron->operador_proveedor_id,
            $dron->marca,
            $dron->modelo,
            $dron->numero_serie,
            $dron->estado->value,
        );
    }
}
