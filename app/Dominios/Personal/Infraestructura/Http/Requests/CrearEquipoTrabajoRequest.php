<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/cuadrillas` (tarea 72, HU-49; pedido del dueño 19/9/2026,
 * tarea "cuadrillas-estadias"): el alta arma la cuadrilla COMPLETA de una
 * sola vez — datos del equipo más piloto, ayudante (con posible segundo) y su
 * dron —obligatorios— y, opcionalmente, vehículo, generador y baterías.
 * `Aplicacion/ArmarCuadrilla` hace el trabajo, dentro de una única
 * transacción.
 *
 * `estado` ya NO se recibe acá (corrección 19/9/2026, invariante 7 de
 * CLAUDE.md): una cuadrilla nace siempre `activo` — lo fija
 * `MaquinaEstadosEquipoTrabajo::crear()`, nunca quien completa el
 * formulario.
 *
 * `dron_id`/`vehiculo_id`/`generador_id`/`bateria_ids.*` no llevan
 * `Rule::exists`: sus tablas cruzan a `Operaciones`/`Mantenimiento` (ADR 0003
 * regla 3) — la existencia y la disponibilidad se verifican DENTRO de
 * `AsignarRecursoEquipo::existeYActivo()`, contra los contratos de lectura de
 * esos módulos, mismo criterio que `AsignarRecursoEquipoRequest`.
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
            'desde' => ['required', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],

            'piloto_id' => [
                'required',
                'integer',
                Rule::exists('per_personas', 'id')->whereNull('deleted_at'),
            ],
            'ayudante_id' => [
                'required',
                'integer',
                Rule::exists('per_personas', 'id')->whereNull('deleted_at'),
            ],
            'ayudante2_id' => [
                'nullable',
                'integer',
                Rule::exists('per_personas', 'id')->whereNull('deleted_at'),
            ],

            'dron_id' => ['required', 'integer', 'min:1'],
            'vehiculo_id' => ['nullable', 'integer', 'min:1'],
            'generador_id' => ['nullable', 'integer', 'min:1'],
            'bateria_ids' => ['nullable', 'array'],
            'bateria_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * La misma persona no puede ocupar dos puestos de la cuadrilla (piloto,
     * ayudante, segundo ayudante) — regla de forma, no de negocio: es la
     * misma persona elegida dos veces en el mismo formulario, distinto del
     * aviso de solapamiento entre cuadrillas DISTINTAS que sí se guarda
     * (`ValidadorSolapamientoVigencias`).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = array_filter(
                [$this->input('piloto_id'), $this->input('ayudante_id'), $this->input('ayudante2_id')],
                fn ($valor) => $valor !== null && $valor !== '',
            );

            if (count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('ayudante_id', __('personal.equipos_trabajo.error_persona_repetida'));
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo.required' => __('personal.equipos_trabajo.error_codigo_requerido'),
            'base_id.required' => __('personal.equipos_trabajo.error_base_requerida'),
            'base_id.exists' => __('personal.validacion.base_invalida'),
            'desde.required' => __('personal.equipos_trabajo.error_desde_requerida'),
            'piloto_id.required' => __('personal.equipos_trabajo.error_piloto_requerido'),
            'piloto_id.exists' => __('personal.validacion.persona_invalida'),
            'ayudante_id.required' => __('personal.equipos_trabajo.error_ayudante_requerido'),
            'ayudante_id.exists' => __('personal.validacion.persona_invalida'),
            'ayudante2_id.exists' => __('personal.validacion.persona_invalida'),
            'dron_id.required' => __('personal.equipos_trabajo.error_dron_requerido'),
            'bateria_ids.*.distinct' => __('personal.equipos_trabajo.error_baterias_repetidas'),
        ];
    }
}
