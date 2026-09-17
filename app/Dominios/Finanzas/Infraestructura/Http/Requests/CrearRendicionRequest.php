<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/rendiciones` (HU-34, tarea 48). La autorización (permiso
 * `finanzas.rendicion.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que `CrearGastoRequest`.
 *
 * `base_id`/`jefe_campo_id` se validan por `exists:` contra las tablas
 * físicas, sin importar los modelos Eloquent de `Personal` (ADR 0003 regla
 * 3, mismo criterio que `CrearGastoRequest`/`CrearAnticipoRequest`).
 */
final class CrearRendicionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'base_id' => ['required', 'integer', Rule::exists('per_bases', 'id')->whereNull('deleted_at')],
            'jefe_campo_id' => ['required', 'integer', Rule::exists('per_personas', 'id')->whereNull('deleted_at')],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'base_id.required' => __('finanzas.rendiciones.error_base_requerida'),
            'base_id.exists' => __('finanzas.rendiciones.error_base_invalida'),
            'jefe_campo_id.required' => __('finanzas.rendiciones.error_jefe_campo_requerido'),
            'jefe_campo_id.exists' => __('finanzas.rendiciones.error_jefe_campo_invalido'),
            'fecha.required' => __('finanzas.rendiciones.error_fecha_requerida'),
        ];
    }
}
