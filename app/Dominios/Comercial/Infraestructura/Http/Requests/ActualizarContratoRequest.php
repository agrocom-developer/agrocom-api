<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/contratos/{contrato}` (HU-23, tarea 34). Mismo criterio que
 * `CrearContratoRequest` para los rangos de `CHECK`, para que `ventanas` sea
 * opcional (HU-47, tarea 70) y para no pedir parámetros de vuelo ni
 * `adelanto_pct` (HU-91, tarea 106) — ver su docblock.
 *
 * `ventanas.*.id`, cuando viene, tiene que pertenecer AL PROPIO contrato que
 * se está editando — nunca a otro (mismo espíritu que
 * `ActualizarClienteRequest` con `contactos.*.id`, invariante 5 de CLAUDE.md
 * aplicada acá al panel interno).
 *
 * `lotes` (pedido del dueño, tarea "contratos-lotes", 16/9/2026): mismo
 * criterio que en `CrearContratoRequest` — acá solo se valida que el ID
 * exista entre `com_lotes` activos. A diferencia de `ventanas.*.id`, no hace
 * falta acotar `lotes.*` a los que ya pertenecen a ESTE contrato: el set
 * enviado es libre de traer lotes nuevos (de cualquier propiedad del
 * cliente); es `Aplicacion/ActualizarContrato` quien decide, vía
 * `Aplicacion/Contrato/VerificadorLotesDelContrato`, si pertenecen al
 * cliente y si hay superficie libre en la propiedad.
 */
final class ActualizarContratoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Contrato|null $contrato */
        $contrato = $this->route('contrato');
        $contratoId = $contrato?->id;

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
            'ventanas' => ['nullable', 'array'],
            'ventanas.*.id' => [
                'nullable',
                'integer',
                Rule::exists('com_contrato_ventanas', 'id')
                    ->where('contrato_id', $contratoId)
                    ->whereNull('deleted_at'),
            ],
            'ventanas.*.hora_inicio' => ['nullable', 'required_with:ventanas.*.hora_fin', 'date_format:H:i'],
            'ventanas.*.hora_fin' => ['nullable', 'required_with:ventanas.*.hora_inicio', 'date_format:H:i', 'after:ventanas.*.hora_inicio'],
            'lotes' => ['required', 'array', 'min:1'],
            'lotes.*' => ['integer', Rule::exists('com_lotes', 'id')->whereNull('deleted_at')],
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
            'ventanas.*.id.exists' => __('comercial.contratos.error_ventana_ajena'),
            'ventanas.*.hora_inicio.required_with' => __('comercial.contratos.error_ventana_incompleta'),
            'ventanas.*.hora_fin.required_with' => __('comercial.contratos.error_ventana_incompleta'),
            'ventanas.*.hora_fin.after' => __('comercial.contratos.error_ventana_horas'),
            'lotes.required' => __('comercial.contratos.error_lotes_requeridos'),
            'lotes.*.exists' => __('comercial.contratos.error_lote_invalido'),
        ];
    }
}
