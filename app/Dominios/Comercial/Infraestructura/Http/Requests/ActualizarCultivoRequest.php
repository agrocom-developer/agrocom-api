<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /panel/cultivos/{cultivo}` (HU-48, tarea 71). Mismas reglas que
 * `CrearCultivoRequest`.
 */
final class ActualizarCultivoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80'],
            'activo' => ['boolean'],
        ];
    }
}
