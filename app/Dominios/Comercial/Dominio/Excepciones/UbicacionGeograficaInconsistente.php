<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use RuntimeException;

/**
 * Guarda de integridad de la cadena Departamento → Provincia → Municipio
 * (adenda 16/9/2026 a ADR 0018 punto 1): la provincia elegida no pertenece
 * al departamento elegido, o el municipio no pertenece a la provincia
 * elegida. El `<select>` en cascada del formulario ya lo impide con JS, pero
 * la guarda real vive en el caso de uso (invariante 5 de CLAUDE.md aplicada
 * al panel interno) — mismo criterio que `LoteAjenoAlCliente`, no un simple
 * `exists` por columna en el FormRequest.
 */
final class UbicacionGeograficaInconsistente extends RuntimeException
{
    public static function porProvinciaAjenaAlDepartamento(): self
    {
        return new self('La provincia seleccionada no pertenece al departamento elegido.');
    }

    public static function porMunicipioAjenoALaProvincia(): self
    {
        return new self('El municipio seleccionado no pertenece a la provincia elegida.');
    }
}
