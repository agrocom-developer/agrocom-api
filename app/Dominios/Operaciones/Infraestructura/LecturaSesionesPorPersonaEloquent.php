<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\DatosSesionesPersona;
use App\Dominios\Operaciones\Contratos\LecturaSesionesPorPersona;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación Eloquent de {@see LecturaSesionesPorPersona}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaResumenCuadrillaEloquent`: esa subcarpeta es solo para modelos.
 *
 * Una sesión cuenta si la persona es su piloto o su auxiliar (`piloto_id` /
 * `auxiliar_id`); el soft delete de `ModeloDominio` deja afuera las dadas de
 * baja. «Validada» es `EstadoSesion::Validado`, el único estado que dispara el
 * devengo (invariante 3): una sesión rechazada sigue `Cerrado`.
 */
final class LecturaSesionesPorPersonaEloquent implements LecturaSesionesPorPersona
{
    public function dePersona(int $personaId): DatosSesionesPersona
    {
        return new DatosSesionesPersona(
            total: $this->sesionesDe($personaId)->count(),
            validadas: $this->sesionesDe($personaId)->where('estado', EstadoSesion::Validado)->count(),
        );
    }

    /** @return Builder<Sesion> */
    private function sesionesDe(int $personaId): Builder
    {
        return Sesion::query()->where(
            fn (Builder $consulta) => $consulta->where('piloto_id', $personaId)->orWhere('auxiliar_id', $personaId),
        );
    }
}
