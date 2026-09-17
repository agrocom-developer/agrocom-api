<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/contratos/{contrato}` (HU-23, tarea 34). Mismo criterio que
 * `CrearContratoRequest` para los rangos de `CHECK` y para no pedir
 * parámetros de vuelo ni `adelanto_pct` (HU-91, tarea 106) — ver su
 * docblock.
 *
 * `lotes` (pedido del dueño, tarea "contratos-lotes", 16/9/2026): mismo
 * criterio que en `CrearContratoRequest` — acá solo se valida que el
 * `lote_id` exista entre `com_lotes` activos y que, si trae horario, sea
 * consistente. A diferencia de un `id` de fila propio (que no existe: el
 * pivote `ContratoLote` se identifica por `lote_id`, no por un `id` que el
 * formulario deba enviar de vuelta), no hace falta acotar `lotes.*.lote_id`
 * a los que ya pertenecen a ESTE contrato: el set enviado es libre de traer
 * lotes nuevos (de cualquier propiedad del cliente); es
 * `Aplicacion/ActualizarContrato` quien decide, vía
 * `Aplicacion/Contrato/VerificadorLotesDelContrato`, si pertenecen al
 * cliente y si hay superficie libre en la propiedad.
 *
 * `lotes.*.hora_inicio`/`lotes.*.hora_fin`: mismo criterio que
 * `CrearContratoRequest` — ver su docblock para el porqué de
 * `required_with` mutuo y `after:lotes.*.hora_inicio`.
 */
final class ActualizarContratoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('com_clientes', 'id')->whereNull('deleted_at')],
            'campania_id' => ['required', 'integer', Rule::exists('cpn_campanias', 'id')->whereNull('deleted_at')],
            'hectareas_contratadas' => ['required', 'numeric', 'gt:0'],
            'aplicaciones_previstas' => ['required', 'integer', 'min:1'],
            'precio_ha' => ['required', 'numeric', 'min:0'],
            'adelanto_monto' => ['nullable', 'numeric', 'min:0'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'brinda_alimentacion' => ['boolean'],
            'brinda_hospedaje' => ['boolean'],
            'brinda_combustible' => ['boolean'],
            'observaciones_logistica' => ['nullable', 'string'],
            'lotes' => ['required', 'array', 'min:1'],
            'lotes.*.lote_id' => ['required', 'integer', Rule::exists('com_lotes', 'id')->whereNull('deleted_at')],
            'lotes.*.hora_inicio' => ['nullable', 'date_format:H:i', 'required_with:lotes.*.hora_fin'],
            'lotes.*.hora_fin' => ['nullable', 'date_format:H:i', 'required_with:lotes.*.hora_inicio', 'after:lotes.*.hora_inicio'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cliente_id.required' => __('comercial.contratos.error_cliente_requerido'),
            'cliente_id.exists' => __('comercial.contratos.error_cliente_invalido'),
            'campania_id.required' => __('comercial.contratos.error_campania_requerida'),
            'campania_id.exists' => __('comercial.contratos.error_campania_invalida'),
            'hectareas_contratadas.required' => __('comercial.contratos.error_hectareas_contratadas_requeridas'),
            'aplicaciones_previstas.required' => __('comercial.contratos.error_aplicaciones_previstas_requeridas'),
            'precio_ha.required' => __('comercial.contratos.error_precio_ha_requerido'),
            'fecha_inicio.required' => __('comercial.contratos.error_fecha_inicio_requerida'),
            'lotes.required' => __('comercial.contratos.error_lotes_requeridos'),
            'lotes.*.lote_id.required' => __('comercial.contratos.error_lote_invalido'),
            'lotes.*.lote_id.exists' => __('comercial.contratos.error_lote_invalido'),
            'lotes.*.hora_inicio.required_with' => __('comercial.contratos.error_lote_horario_incompleto'),
            'lotes.*.hora_fin.required_with' => __('comercial.contratos.error_lote_horario_incompleto'),
            'lotes.*.hora_fin.after' => __('comercial.contratos.error_lote_horario_invalido'),
        ];
    }
}
