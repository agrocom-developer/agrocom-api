<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/drones/{dron}` (HU-27, tarea 36). Mismo criterio que
 * `CrearDronRequest` para el identificador (sin regla `unique`, ver su
 * docblock) y para `capacidad_kg` (HU-81, tarea 96: sin `Rule::in()`).
 */
final class ActualizarDronRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identificador' => ['required', 'string', 'max:40'],
            'modelo' => ['nullable', 'string', 'max:40'],
            'capacidad_l' => ['nullable', Rule::in([30, 50, 60])],
            'capacidad_kg' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
