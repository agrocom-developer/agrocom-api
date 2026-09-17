<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/equipos-trabajo` (tarea 72, HU-49). La autorización (permiso
 * `personal.equipo_trabajo.crear`) se verifica en el controlador, contra el
 * rol activo — no acá, mismo criterio que el resto del panel.
 *
 * `base_id` es obligatorio (a diferencia de `man_generadores.base_id`): un
 * equipo de trabajo nace asignado a una base, es lo que ubica a la
 * cuadrilla (ver el docblock de la migración `per_equipos_trabajo`).
 *
 * `codigo` no lleva regla `unique`: el índice único real es PARCIAL (solo
 * entre filas activas), y la violación se atrapa en `CrearEquipoTrabajo` y
 * se traduce ahí — mismo criterio que `CrearCampaniaRequest`.
 */
final class CrearEquipoTrabajoRequest extends FormRequest
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
