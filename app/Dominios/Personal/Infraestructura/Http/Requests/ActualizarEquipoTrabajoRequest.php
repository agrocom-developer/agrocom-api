<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/cuadrillas/{equipoTrabajo}` (tarea 72, HU-49). Solo los datos
 * descriptivos del equipo — código, nombre, base y vigencia—: integrantes,
 * recursos y accesorios se editan con sus propios endpoints.
 *
 * `estado` ya NO se recibe acá (corrección 19/9/2026, invariante 7 de
 * CLAUDE.md): editar una cuadrilla nunca toca su estado — eso pasa por
 * `POST /panel/cuadrillas/{equipoTrabajo}/estado`
 * (`CambiarEstadoEquipoTrabajoRequest`).
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
            'desde.required' => __('personal.equipos_trabajo.error_desde_requerida'),
        ];
    }
}
