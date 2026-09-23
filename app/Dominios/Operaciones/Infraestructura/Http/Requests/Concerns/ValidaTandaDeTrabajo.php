<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests\Concerns;

use App\Dominios\Finanzas\Contratos\ModalidadPago;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas por `AsignarEquipoOrdenRequest` (`/panel/reparto-cuadrillas/{orden}`,
 * pantalla vieja — HU-70/92) y `CrearOrdenTrabajoRequest` (`/panel/trabajos`,
 * pantalla nueva de la reforma 18/9/2026): ambas terminan llamando a
 * `Aplicacion/AsignarEquiposOrden::ejecutar()` con la MISMA forma de datos
 * (`parametros` compartidos de la tanda + `equipos[]` con sus lotes/turno) —
 * evita repetir el mismo bloque de reglas en los dos Requests.
 *
 * Las reglas de las indicaciones compartidas (`parametros.*`, sin `equipos`)
 * viven en {@see ValidaIndicacionesOrdenTrabajo} (tarea 127): las reusa
 * también `ActualizarOrdenTrabajoRequest`, que no toca el reparto.
 *
 * Solo valida FORMA: que cada equipo/lote exista, que las hectáreas sean
 * positivas, y que el turno sea uno de los tres valores (sus horas son
 * opcionales, pero si vienen las dos el fin es posterior al inicio). La
 * vigencia del equipo, el tope de hectáreas por lote y que la orden esté
 * vigente NO se validan acá: son las guardas de negocio de `AsignarEquiposOrden`.
 */
trait ValidaTandaDeTrabajo
{
    use ValidaIndicacionesOrdenTrabajo;

    /** @return array<string, mixed> */
    private function reglasParametrosYEquipos(?int $ordenId): array
    {
        return [
            ...$this->reglasIndicaciones(),
            'parametros.calda' => ['nullable', 'array'],
            'parametros.calda.*.producto' => ['required_with:parametros.calda', 'string', 'max:120'],
            'parametros.calda.*.cantidad' => ['required_with:parametros.calda', 'numeric', 'gt:0'],
            'parametros.calda.*.unidad' => ['required_with:parametros.calda', Rule::in(['l', 'ml', 'kg', 'g'])],

            'equipos' => ['required', 'array', 'min:1'],
            'equipos.*.equipo_trabajo_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('per_equipos_trabajo', 'id')->whereNull('deleted_at'),
            ],
            // Condición de pago del equipo (ADR 0023): una tarifa del catálogo,
            // o —si se negoció para este trabajo— modalidad, montos y motivo.
            'equipos.*.pago' => ['required', 'array'],
            'equipos.*.pago.negociado' => ['nullable', 'boolean'],
            'equipos.*.pago.tarifa_id' => [
                'nullable',
                'integer',
                'required_unless:equipos.*.pago.negociado,1',
                Rule::exists('fin_tarifas', 'id')->whereNull('deleted_at'),
            ],
            'equipos.*.pago.modalidad' => ['nullable', 'required_if:equipos.*.pago.negociado,1', Rule::enum(ModalidadPago::class)],
            'equipos.*.pago.monto_piloto' => ['nullable', 'required_if:equipos.*.pago.negociado,1', 'numeric', 'min:0'],
            'equipos.*.pago.monto_auxiliar' => ['nullable', 'required_if:equipos.*.pago.negociado,1', 'numeric', 'min:0'],
            'equipos.*.pago.motivo' => ['nullable', 'required_if:equipos.*.pago.negociado,1', 'string', 'max:255'],
            'equipos.*.lotes' => ['required', 'array', 'min:1'],
            'equipos.*.lotes.*.lote_id' => [
                'required',
                'integer',
                Rule::exists('ope_orden_lotes', 'lote_id')->where('orden_id', $ordenId)->whereNull('deleted_at'),
            ],
            'equipos.*.lotes.*.hectareas' => ['required', 'numeric', 'gt:0'],
            'equipos.*.lotes.*.turno' => ['required', Rule::in(['manana', 'noche', 'todo_el_dia'])],
            // Las horas son una referencia opcional (pedido del dueño, 21/9/2026):
            // lo que se exige es el turno; el horario real lo registra el equipo.
            'equipos.*.lotes.*.turno_hora_inicio' => ['nullable', 'date_format:H:i'],
            'equipos.*.lotes.*.turno_hora_fin' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Cross-checks: los de {@see ValidaIndicacionesOrdenTrabajo::validarIndicaciones()}
     * (humedad, Ph/litros/kilos según el insumo) más el horario de turno de
     * cada lote, propio del reparto — esta clase es la única que lo valida.
     */
    private function validarParametrosYEquipos(Validator $validator, ?OrdenAplicacion $orden): void
    {
        $this->validarIndicaciones($validator, $orden);

        foreach ((array) $this->input('equipos', []) as $indiceEquipo => $equipo) {
            foreach ((array) ($equipo['lotes'] ?? []) as $indiceLote => $lote) {
                $inicio = $lote['turno_hora_inicio'] ?? null;
                $fin = $lote['turno_hora_fin'] ?? null;

                if ($inicio !== null && $fin !== null && $inicio !== '' && $fin !== '' && $fin <= $inicio) {
                    $validator->errors()->add(
                        "equipos.{$indiceEquipo}.lotes.{$indiceLote}.turno_hora_fin",
                        __('operaciones.asignacion_equipos.error_turno_hora_rango'),
                    );
                }
            }
        }
    }
}
