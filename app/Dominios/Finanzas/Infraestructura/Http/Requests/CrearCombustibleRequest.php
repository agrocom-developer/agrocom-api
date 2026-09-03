<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/combustible` (HU-35, tarea 49). La autorización (permiso
 * `finanzas.combustible.crear`) se verifica en el controlador, contra el
 * rol activo — no acá, mismo criterio que `CrearGastoRequest`.
 *
 * `litros`/`monto` > 0 replican los `CHECK` de
 * `database/migrations/2026_09_03_100006_create_fin_combustibles_table` —
 * así el usuario ve un error de validación de Laravel, nunca el
 * `QueryException` crudo de Postgres.
 *
 * `destino` valida contra el mismo enum del `CHECK` de la migración
 * (`generador`/`vehiculo`): un valor fuera de esos dos vuelve 422, nunca el
 * 500 de un `QueryException`.
 */
final class CrearCombustibleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'base_id' => ['required', 'integer', Rule::exists('per_bases', 'id')->whereNull('deleted_at')],
            'destino' => ['required', Rule::in(['generador', 'vehiculo'])],
            'litros' => ['required', 'numeric', 'gt:0'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'descripcion' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'base_id.required' => __('finanzas.combustible.error_base_requerida'),
            'base_id.exists' => __('finanzas.combustible.error_base_invalida'),
            'destino.required' => __('finanzas.combustible.error_destino_requerido'),
            'destino.in' => __('finanzas.combustible.error_destino_invalido'),
        ];
    }
}
