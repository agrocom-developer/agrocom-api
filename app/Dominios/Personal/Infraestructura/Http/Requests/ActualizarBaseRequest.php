<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /panel/bases/{base}` (HU-26, tarea 37). Mismas reglas que
 * `CrearBaseRequest`.
 */
final class ActualizarBaseRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'ubicacion' => ['nullable', 'string', 'max:200'],
        ];
    }
}
