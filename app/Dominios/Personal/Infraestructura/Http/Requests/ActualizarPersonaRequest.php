<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use App\Dominios\Personal\Dominio\RolOperativoPersona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/personas/{persona}` (HU-26, tarea 37). Mismas reglas que
 * `CrearPersonaRequest`.
 */
final class ActualizarPersonaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'rol' => ['required', Rule::enum(RolOperativoPersona::class)],
            'base_id' => [
                'nullable',
                'integer',
                Rule::exists('per_bases', 'id')->whereNull('deleted_at'),
            ],
            'tarifa_ha' => ['nullable', 'numeric', 'min:0'],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'base_id.exists' => 'La base seleccionada no es válida.',
        ];
    }
}
