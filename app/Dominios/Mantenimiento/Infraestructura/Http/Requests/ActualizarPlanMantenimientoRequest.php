<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /panel/planes-mantenimiento/{plan}` (HU-38, tarea 54). Mismas reglas
 * que `CrearPlanMantenimientoRequest` — ver ese docblock.
 */
final class ActualizarPlanMantenimientoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'modelo' => ['required', 'string', 'max:40'],
            'tarea' => ['required', 'string'],
            'horas_umbral' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
