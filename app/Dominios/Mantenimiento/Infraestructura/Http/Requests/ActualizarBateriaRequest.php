<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Requests;

use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/baterias/{bateria}` (HU-39, tarea 51). Mismas reglas que
 * `CrearBateriaRequest` — ver ese docblock.
 */
final class ActualizarBateriaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identificador' => ['required', 'string', 'max:40'],
            'ciclos_acumulados' => ['required', 'integer', 'min:0'],
            'base_id' => [
                'nullable',
                'integer',
                Rule::exists('per_bases', 'id')->whereNull('deleted_at'),
            ],
            'estado' => ['required', Rule::enum(EstadoBateria::class)],
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
