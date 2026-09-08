<?php

namespace App\Dominios\Campania\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /panel/campanias/{campania}` (ADR 0015 punto 1, tarea 69). Mismas
 * reglas que `CrearCampaniaRequest` — ver ese docblock para el porqué de que
 * `codigo` no lleve `unique`.
 */
final class ActualizarCampaniaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['nullable', 'string', 'max:150'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }
}
