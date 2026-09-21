<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Infraestructura\Http\Requests\Concerns\ValidaTandaDeTrabajo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/reparto-cuadrillas/{orden}` (HU-70, tarea 85; rediseñado por
 * HU-92, tarea 107; reforma 18/9/2026 — "Orden de Trabajo"/tandas): esta
 * pantalla queda como la vía RÁPIDA de repartir una orden entre equipos, sin
 * el paso explícito de tanda que ofrece `/panel/trabajos` (pantalla nueva,
 * `CrearOrdenTrabajoRequest`) — ambas terminan en el mismo caso de uso
 * (`Aplicacion/CrearOrdenTrabajo`), así que comparten las mismas reglas vía
 * {@see ValidaTandaDeTrabajo}.
 *
 * Forma del payload (igual a la nueva pantalla): `parametros` (clima/vuelo +
 * Ph/calda, compartidos por toda la confirmación) + `equipos[]` (cada uno con
 * `equipo_trabajo_id`, `lotes[]` con `lote_id`/`hectareas`/`turno`/horario).
 *
 * La autorización (permiso `operaciones.orden.asignar_equipos`) se verifica
 * en el controlador, contra el rol activo — no acá, mismo criterio que el
 * resto del panel.
 */
final class AsignarEquipoOrdenRequest extends FormRequest
{
    use ValidaTandaDeTrabajo;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reglasParametrosYEquipos($this->route('orden')?->id);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarParametrosYEquipos($validator, $this->route('orden'));
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'equipos.required' => __('operaciones.asignacion_equipos.error_equipos_requerido'),
            'equipos.*.equipo_trabajo_id.required' => __('operaciones.asignacion_equipos.error_equipo_requerido'),
            'equipos.*.lotes.required' => __('operaciones.asignacion_equipos.error_lotes_requerido'),
            'equipos.*.lotes.*.lote_id.required' => __('operaciones.asignacion_equipos.error_lote_requerido'),
            'equipos.*.lotes.*.hectareas.required' => __('operaciones.asignacion_equipos.error_hectareas_requerido'),
            'equipos.*.lotes.*.turno.required' => __('operaciones.asignacion_equipos.error_turno_requerido'),
            'equipos.*.lotes.*.turno_hora_inicio.required' => __('operaciones.asignacion_equipos.error_turno_hora_requerida'),
            'equipos.*.lotes.*.turno_hora_fin.required' => __('operaciones.asignacion_equipos.error_turno_hora_requerida'),
        ];
    }
}
