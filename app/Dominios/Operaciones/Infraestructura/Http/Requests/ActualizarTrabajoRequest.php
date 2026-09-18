<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/trabajos/detalle/{trabajo}` (HU-93, tarea 108; ruta renombrada
 * en la reforma 18/9/2026 — ver docblock de `TrabajosController`). La
 * autorización (permiso `operaciones.trabajo.editar`) se verifica en el
 * controlador, contra el rol activo — no acá, mismo criterio que el resto
 * del panel.
 *
 * Solo valida FORMA: que el lote pertenezca a `ope_orden_lotes` DE LA ORDEN
 * de este trabajo (mismo criterio que `AsignarEquipoOrdenRequest`), que el
 * equipo (si se eligió alguno) exista, que las hectáreas sean un número
 * positivo, y que turno/horas (si se cargó alguno) vengan los tres juntos y
 * en rango. La vigencia del equipo, el tope de hectáreas por lote y que el
 * trabajo no esté `validado` NO se validan acá: son las guardas de negocio
 * de `Aplicacion/ActualizarTrabajo`.
 */
final class ActualizarTrabajoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $ordenId = $this->route('trabajo')?->orden_id;

        return [
            'lote_id' => [
                'required',
                'integer',
                Rule::exists('ope_orden_lotes', 'lote_id')->where('orden_id', $ordenId)->whereNull('deleted_at'),
            ],
            'equipo_trabajo_id' => [
                'nullable',
                'integer',
                Rule::exists('per_equipos_trabajo', 'id')->whereNull('deleted_at'),
            ],
            'hectareas_declaradas' => ['required', 'numeric', 'gt:0'],
            'turno' => ['nullable', Rule::in(['manana', 'noche', 'todo_el_dia'])],
            'turno_hora_inicio' => ['nullable', 'required_with:turno', 'date_format:H:i'],
            'turno_hora_fin' => ['nullable', 'required_with:turno', 'date_format:H:i', 'after:turno_hora_inicio'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lote_id.required' => __('operaciones.trabajos.error_lote_requerido'),
            'lote_id.exists' => __('operaciones.trabajos.error_lote_no_pertenece'),
            'equipo_trabajo_id.exists' => __('operaciones.trabajos.error_equipo_no_existe'),
            'hectareas_declaradas.required' => __('operaciones.trabajos.error_hectareas_requerido'),
            'hectareas_declaradas.gt' => __('operaciones.trabajos.error_hectareas_positivo'),
            'turno_hora_inicio.required_with' => __('operaciones.trabajos.error_turno_hora_requerida'),
            'turno_hora_fin.required_with' => __('operaciones.trabajos.error_turno_hora_requerida'),
            'turno_hora_fin.after' => __('operaciones.trabajos.error_turno_hora_rango'),
        ];
    }
}
