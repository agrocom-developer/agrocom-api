<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/equipos-trabajo/{equipoTrabajo}` (tarea 72, HU-49). Mismas
 * reglas que `CrearEquipoTrabajoRequest` — ver su docblock.
 */
final class ActualizarEquipoTrabajoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['nullable', 'string', 'max:150'],
            'base_id' => [
                'required',
                'integer',
                Rule::exists('per_bases', 'id')->whereNull('deleted_at'),
            ],
            'estado' => ['required', Rule::enum(EstadoEquipoTrabajo::class)],
            'desde' => ['required', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo.required' => __('personal.equipos_trabajo.error_codigo_requerido'),
            'base_id.required' => __('personal.equipos_trabajo.error_base_requerida'),
            'base_id.exists' => __('personal.validacion.base_invalida'),
            'estado.required' => __('personal.equipos_trabajo.error_estado_requerido'),
            'desde.required' => __('personal.equipos_trabajo.error_desde_requerida'),
        ];
    }
}
