<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/fichas-dron/{fichaDron}` (HU-82, tarea 97). Mismas reglas que
 * `CrearFichaDronRequest` — ver ese docblock.
 */
final class ActualizarFichaDronRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identificador_dron' => [
                'required',
                'string',
                'max:40',
                Rule::exists('ope_drones', 'identificador')->whereNull('deleted_at'),
            ],
            'numero_serie' => ['nullable', 'string', 'max:255'],
            'chasis' => ['nullable', 'string', 'max:255'],
            'version_software' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'serie_control' => ['nullable', 'string', 'max:255'],
            'tiene_cargador_control' => ['boolean'],
            'tiene_modem' => ['boolean'],
            'tiene_maletin' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'identificador_dron.required' => __('mantenimiento.validacion.identificador_dron_requerido'),
            'identificador_dron.exists' => __('mantenimiento.validacion.dron_invalido'),
        ];
    }
}
