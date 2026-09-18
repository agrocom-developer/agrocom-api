<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Dominio\TipoAplicacion;
use App\Dominios\Operaciones\Dominio\TipoInsumo;
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
 *
 * Los 8 campos de límites climáticos y parámetros de vuelo YA NO se piden
 * acá — ver el docblock de `CrearOrdenRequest` (misma nota, mismas reglas).
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
            'categoria_insumo_id' => ['required', 'integer', Rule::exists('ope_categorias_insumo', 'id')->whereNull('deleted_at')],
            'litros_ha' => ['nullable', 'numeric', 'gt:0'],
            'kilos_por_vuelo' => ['nullable', 'numeric', 'gt:0'],
            'observaciones' => ['nullable', 'string'],
            'emitida_por_contacto_id' => ['nullable', 'integer', Rule::exists('com_cliente_contactos', 'id')->whereNull('deleted_at')],
            'fecha_emision' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarCampoSegunCategoriaInsumo($validator);

            $contratoId = $this->input('contrato_id');
            $clienteDelContrato = $contratoId !== null && $contratoId !== ''
                ? DB::table('com_contratos')->where('id', $contratoId)->value('cliente_id')
                : null;

            foreach ((array) $this->input('lotes', []) as $indice => $lote) {
                $loteId = $lote['lote_id'] ?? null;
                $hectareasSolicitadas = $lote['hectareas_solicitadas'] ?? null;

                if ($loteId === null || $loteId === '') {
                    continue;
                }

                $filaLote = DB::table('com_lotes as l')
                    ->join('com_propiedades as p', 'p.id', '=', 'l.propiedad_id')
                    ->where('l.id', $loteId)
                    ->first(['l.hectareas', 'p.cliente_id']);

                if ($filaLote === null) {
                    continue;
                }

                if ($clienteDelContrato !== null && (int) $filaLote->cliente_id !== (int) $clienteDelContrato) {
                    $validator->errors()->add(
                        "lotes.{$indice}.lote_id",
                        __('operaciones.ordenes.error_lote_de_otro_cliente'),
                    );
                }

                if ($hectareasSolicitadas === null || $hectareasSolicitadas === '') {
                    continue;
                }

                if (BigDecimal::of((string) $hectareasSolicitadas)->isGreaterThan(BigDecimal::of((string) $filaLote->hectareas))) {
                    $validator->errors()->add(
                        "lotes.{$indice}.hectareas_solicitadas",
                        __('operaciones.ordenes.error_hectareas_solicitadas_superan_lote'),
                    );
                }
            }
        });
    }

    /** Mismo criterio que {@see CrearOrdenRequest::validarCampoSegunCategoriaInsumo()}. */
    private function validarCampoSegunCategoriaInsumo(Validator $validator): void
    {
        $categoriaInsumoId = $this->input('categoria_insumo_id');

        if ($categoriaInsumoId === null || $categoriaInsumoId === '') {
            return;
        }

        $tipoInsumo = DB::table('ope_categorias_insumo')->where('id', $categoriaInsumoId)->value('tipo_insumo');

        if ($tipoInsumo === TipoInsumo::Solido->value) {
            $kilosPorVuelo = $this->input('kilos_por_vuelo');

            if ($kilosPorVuelo === null || $kilosPorVuelo === '') {
                $validator->errors()->add('kilos_por_vuelo', __('operaciones.ordenes.error_kilos_por_vuelo_requerido'));
            }
        } elseif ($tipoInsumo === TipoInsumo::Liquido->value) {
            $litrosHa = $this->input('litros_ha');

            if ($litrosHa === null || $litrosHa === '') {
                $validator->errors()->add('litros_ha', __('operaciones.ordenes.error_litros_ha_requerido'));
            }
        }
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
            'lotes.*.hectareas_solicitadas.required' => __('operaciones.ordenes.error_hectareas_solicitadas_requeridas'),
            'cantidad_equipos_necesarios.required' => __('operaciones.ordenes.error_cantidad_equipos_requerida'),
            'nro_aplicacion.required' => __('operaciones.ordenes.error_nro_aplicacion_requerido'),
            'tipo_aplicacion.required' => __('operaciones.ordenes.error_tipo_aplicacion_requerido'),
            'fecha_emision.required' => __('operaciones.ordenes.error_fecha_emision_requerida'),
            'categoria_insumo_id.required' => __('operaciones.ordenes.error_categoria_insumo_requerida'),
            'categoria_insumo_id.exists' => __('operaciones.ordenes.error_categoria_insumo_invalida'),
            'emitida_por_contacto_id.exists' => __('operaciones.ordenes.error_contacto_invalido'),
        ];
    }
}
