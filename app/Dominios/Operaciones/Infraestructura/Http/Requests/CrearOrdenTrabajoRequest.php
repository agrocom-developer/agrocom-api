<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\Concerns\ValidaTandaDeTrabajo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/trabajos` (reforma 18/9/2026, "Orden de Trabajo"): a
 * diferencia de `AsignarEquipoOrdenRequest` (route-bound a `{orden}`), acá la
 * orden de aplicación se elige DENTRO del formulario — `orden_id` viaja como
 * campo más, no como parámetro de ruta, porque `/panel/trabajos/crear` no
 * cuelga de una orden puntual (se puede llegar sin preseleccionar ninguna).
 *
 * Mismas reglas de `parametros`/`equipos[]` que la pantalla vieja, vía
 * {@see ValidaTandaDeTrabajo} — ambas construyen la misma forma para
 * `Aplicacion/CrearOrdenTrabajo::ejecutar()`.
 */
final class CrearOrdenTrabajoRequest extends FormRequest
{
    use ValidaTandaDeTrabajo;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'orden_id' => [
                'required',
                'integer',
                Rule::exists('ope_ordenes_aplicacion', 'id')
                    ->where('estado', EstadoOrdenAplicacion::Vigente->value)
                    ->whereNull('deleted_at'),
            ],
            ...$this->reglasParametrosYEquipos($this->integer('orden_id') ?: null),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ordenId = $this->integer('orden_id');
            $orden = $ordenId !== 0 ? OrdenAplicacion::query()->find($ordenId) : null;

            $this->validarParametrosYEquipos($validator, $orden);
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'orden_id.required' => __('operaciones.ordenes_trabajo.error_orden_requerida'),
            'orden_id.exists' => __('operaciones.ordenes_trabajo.error_orden_no_vigente'),
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
