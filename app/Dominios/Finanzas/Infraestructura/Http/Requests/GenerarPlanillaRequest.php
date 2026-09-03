<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/planillas` (HU-30, tarea 44). La autorización (permiso
 * `finanzas.planilla.generar`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que `CrearAnticipoRequest`.
 *
 * `periodo` replica el mismo formato `YYYY-MM` que valida
 * `ListarDevengosPersona`/`ListarAnticipos` y el `CHECK` de
 * `create_fin_planillas_table` — así el usuario ve un error de validación de
 * Laravel, nunca el `QueryException` crudo de Postgres.
 */
final class GenerarPlanillaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'periodo' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ];
    }
}
