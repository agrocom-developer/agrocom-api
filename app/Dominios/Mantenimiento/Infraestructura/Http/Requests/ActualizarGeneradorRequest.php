<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Requests;

use App\Dominios\Mantenimiento\Dominio\EstadoGenerador;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/generadores/{generador}` (tarea 72, HU-49). Mismas reglas que
 * `CrearGeneradorRequest` — ver ese docblock, incluida `horas_inicial`/
 * `horas_actual` (HU-86, tarea 101).
 */
final class ActualizarGeneradorRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identificador' => ['required', 'string', 'max:40'],
            'modelo' => ['nullable', 'string', 'max:60'],
            'base_id' => [
                'nullable',
                'integer',
                Rule::exists('per_bases', 'id')->whereNull('deleted_at'),
            ],
            'estado' => ['required', Rule::enum(EstadoGenerador::class)],
            'horas_inicial' => ['nullable', 'numeric', 'min:0'],
            'horas_actual' => ['nullable', 'numeric', 'min:0', 'gte:horas_inicial'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'identificador.required' => __('mantenimiento.validacion.generador_identificador_requerido'),
            'estado.required' => __('mantenimiento.validacion.generador_estado_requerido'),
            'base_id.exists' => __('mantenimiento.validacion.base_invalida'),
            'horas_actual.gte' => __('mantenimiento.validacion.horas_actual_menor'),
        ];
    }
}
