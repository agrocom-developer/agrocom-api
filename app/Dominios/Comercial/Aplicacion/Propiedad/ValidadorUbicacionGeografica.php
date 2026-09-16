<?php

namespace App\Dominios\Comercial\Aplicacion\Propiedad;

use App\Dominios\Comercial\Dominio\Excepciones\UbicacionGeograficaInconsistente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Municipio;
use App\Dominios\Comercial\Infraestructura\Eloquent\Provincia;

/**
 * Guarda de integridad de la cadena Departamento → Provincia → Municipio
 * (adenda 16/9/2026 a ADR 0018 punto 1). Colaborador compartido de
 * `CrearPropiedad` y `ActualizarPropiedad`, mismo criterio que
 * `GuardadoLote`/`VerificadorLotesDelContrato`: una sola guarda, sin
 * duplicarla en cada caso de uso.
 */
final class ValidadorUbicacionGeografica
{
    /** @throws UbicacionGeograficaInconsistente */
    public static function validar(?int $departamentoId, ?int $provinciaId, ?int $municipioId): void
    {
        if ($provinciaId !== null) {
            $provincia = Provincia::query()->find($provinciaId);

            if ($provincia === null || $provincia->departamento_id !== $departamentoId) {
                throw UbicacionGeograficaInconsistente::porProvinciaAjenaAlDepartamento();
            }
        }

        if ($municipioId !== null) {
            $municipio = Municipio::query()->find($municipioId);

            if ($municipio === null || ($provinciaId !== null && $municipio->provincia_id !== $provinciaId)) {
                throw UbicacionGeograficaInconsistente::porMunicipioAjenoALaProvincia();
            }
        }
    }
}
