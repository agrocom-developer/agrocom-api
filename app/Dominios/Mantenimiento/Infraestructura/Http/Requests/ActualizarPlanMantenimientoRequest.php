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

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'modelo.required' => __('mantenimiento.validacion.modelo_requerido'),
            'tarea.required' => __('mantenimiento.validacion.tarea_requerida'),
            'horas_umbral.required' => __('mantenimiento.validacion.horas_umbral_requerido'),
        ];
    }
}
