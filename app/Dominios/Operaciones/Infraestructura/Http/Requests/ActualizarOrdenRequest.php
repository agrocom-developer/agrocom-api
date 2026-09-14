<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Dominio\TipoAplicacion;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/ordenes/{orden}` (HU-25, tarea 38; `lotes[]` reemplaza
 * `lote_id` por HU-92, tarea 107). Mismas reglas que `CrearOrdenRequest` —
 * ver su docblock para el porqué de cada rango y de no exigir contrato
 * `vigente`. Que la orden siga siendo editable (solo `emitida`) no se valida
 * acá: es una regla de ESTADO, no de forma, y vive en
 * `Aplicacion/ActualizarOrden` (invariante 7) para que también la respete un
 * `PUT` directo.
 */
final class ActualizarOrdenRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contrato_id' => ['required', 'integer', Rule::exists('com_contratos', 'id')->whereNull('deleted_at')],
            'lotes' => ['required', 'array', 'min:1'],
            'lotes.*.lote_id' => ['required', 'integer', 'distinct', Rule::exists('com_lotes', 'id')->whereNull('deleted_at')],
            'lotes.*.hectareas_solicitadas' => ['required', 'numeric', 'gt:0'],
            'cantidad_equipos_necesarios' => ['required', 'integer', 'min:1'],
            'nro_aplicacion' => ['required', 'integer', 'min:1'],
            'tipo_aplicacion' => ['required', Rule::enum(TipoAplicacion::class)],
            'litros_ha' => ['required', 'numeric', 'gt:0'],
            'humedad_min_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'humedad_max_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'viento_max_kmh' => ['nullable', 'numeric', 'gt:0'],
            'temperatura_max_c' => ['nullable', 'numeric', 'gt:-10', 'lt:60'],
            'velocidad_max_kmh' => ['nullable', 'numeric', 'gt:0'],
            'altura_vuelo_m' => ['nullable', 'numeric', 'gt:0'],
            'velocidad_vuelo_kmh' => ['nullable', 'numeric', 'gt:0'],
            'ancho_pasada_m' => ['nullable', 'numeric', 'gt:0'],
            'observaciones' => ['nullable', 'string'],
            'emitida_por_contacto_id' => ['nullable', 'integer', Rule::exists('com_cliente_contactos', 'id')->whereNull('deleted_at')],
            'fecha_emision' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $minimo = $this->input('humedad_min_pct');
            $maximo = $this->input('humedad_max_pct');

            if ($minimo !== null && $minimo !== '' && $maximo !== null && $maximo !== '' && (float) $minimo > (float) $maximo) {
                $validator->errors()->add('humedad_min_pct', __('operaciones.ordenes.error_humedad_rango'));
            }

            foreach ((array) $this->input('lotes', []) as $indice => $lote) {
                $loteId = $lote['lote_id'] ?? null;
                $hectareasSolicitadas = $lote['hectareas_solicitadas'] ?? null;

                if ($loteId === null || $loteId === '' || $hectareasSolicitadas === null || $hectareasSolicitadas === '') {
                    continue;
                }

                $hectareasLote = DB::table('com_lotes')->where('id', $loteId)->value('hectareas');

                if ($hectareasLote !== null && BigDecimal::of((string) $hectareasSolicitadas)->isGreaterThan(BigDecimal::of((string) $hectareasLote))) {
                    $validator->errors()->add(
                        "lotes.{$indice}.hectareas_solicitadas",
                        __('operaciones.ordenes.error_hectareas_solicitadas_superan_lote'),
                    );
                }
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contrato_id.required' => __('operaciones.ordenes.error_contrato_requerido'),
            'contrato_id.exists' => __('operaciones.ordenes.error_contrato_invalido'),
            'lotes.required' => __('operaciones.ordenes.error_lotes_requerido'),
            'lotes.*.lote_id.required' => __('operaciones.ordenes.error_lote_requerido'),
            'lotes.*.lote_id.exists' => __('operaciones.ordenes.error_lote_invalido'),
            'lotes.*.lote_id.distinct' => __('operaciones.ordenes.error_lote_repetido'),
            'emitida_por_contacto_id.exists' => __('operaciones.ordenes.error_contacto_invalido'),
        ];
    }
}
