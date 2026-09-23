<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Finanzas\Contratos\ModalidadPago;
use App\Dominios\Operaciones\Dominio\EstadoTableroTrabajo;
use App\Dominios\Operaciones\Dominio\PoliticaEdicionOrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\Concerns\ValidaIndicacionesOrdenTrabajo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/trabajos/{ordenTrabajo}` (tarea 127): edición de la cabecera de
 * una Orden de Trabajo —indicaciones compartidas, mismas reglas que el alta
 * vía {@see ValidaIndicacionesOrdenTrabajo}— y, por equipo, su condición de
 * pago (ADR 0023). El reparto (equipos, lotes, hectáreas, turno) NO se edita
 * acá, así que no hay reglas de `equipos.*.lotes.*`.
 *
 * `equipos` viene indexado por `equipo_trabajo_id` (no por posición, a
 * diferencia del alta): la edición no agrega ni quita equipos, solo corrige
 * la condición de uno existente, así que la clave del array ES el id.
 *
 * Que la Orden de Trabajo siga siendo editable, y que cada equipo admita que
 * se le toque la condición, NO se valida acá: son reglas de ESTADO, no de
 * forma, y viven en `Aplicacion/ActualizarOrdenTrabajo`
 * (`PoliticaEdicionOrdenTrabajo`, invariante 7 de CLAUDE.md).
 */
final class ActualizarOrdenTrabajoRequest extends FormRequest
{
    use ValidaIndicacionesOrdenTrabajo;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->reglasIndicaciones(),
            'motivo_correccion' => [Rule::requiredIf($this->exigeMotivo()), 'nullable', 'string', 'max:1000'],

            'equipos' => ['nullable', 'array'],
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarIndicaciones($validator, $this->ordenTrabajo()->orden);
        });
    }

    /** Una tanda con algún trabajo ya `cerrado` pide motivo ({@see PoliticaEdicionOrdenTrabajo}). */
    private function exigeMotivo(): bool
    {
        return PoliticaEdicionOrdenTrabajo::exigeMotivo($this->estadosTrabajos());
    }

    /** @return list<EstadoTableroTrabajo> */
    private function estadosTrabajos(): array
    {
        return $this->ordenTrabajo()->trabajos
            ->map(fn (Trabajo $trabajo) => $trabajo->estadoTablero())
            ->all();
    }

    private function ordenTrabajo(): OrdenTrabajo
    {
        /** @var OrdenTrabajo $ordenTrabajo */
        $ordenTrabajo = $this->route('ordenTrabajo');
        $ordenTrabajo->loadMissing('trabajos.sesiones', 'orden.categoriaInsumo');

        return $ordenTrabajo;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo_correccion.required' => __('operaciones.ordenes_trabajo.error_motivo_correccion_requerido'),
            'equipos.*.pago.tarifa_id.required_unless' => __('operaciones.ordenes_trabajo.error_tarifa_requerida'),
            'equipos.*.pago.tarifa_id.exists' => __('operaciones.ordenes_trabajo.error_tarifa_no_disponible'),
            'equipos.*.pago.modalidad.required_if' => __('operaciones.ordenes_trabajo.error_pago_modalidad_requerida'),
            'equipos.*.pago.monto_piloto.required_if' => __('operaciones.ordenes_trabajo.error_pago_monto_requerido'),
            'equipos.*.pago.monto_auxiliar.required_if' => __('operaciones.ordenes_trabajo.error_pago_monto_requerido'),
            'equipos.*.pago.motivo.required_if' => __('operaciones.ordenes_trabajo.error_pago_motivo_requerido'),
        ];
    }
}
