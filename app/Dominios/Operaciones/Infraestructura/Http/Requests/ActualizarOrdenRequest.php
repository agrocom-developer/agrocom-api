<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Dominio\TipoAplicacion;
use App\Dominios\Operaciones\Dominio\TipoInsumo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/ordenes/{orden}` (HU-25 reforma Entrega 1, 18/9/2026). Ya no se
 * pueden cambiar: contrato, lotes, ni número de aplicación. Solo se edita:
 * tipo, categoría de insumo, dosis, cantidad de equipos, contacto, fecha
 * de emisión, observaciones.
 *
 * Que la orden siga siendo editable (solo `emitida`) no se valida acá: es una
 * regla de ESTADO, no de forma, y vive en `Aplicacion/ActualizarOrden`
 * (invariante 7).
 */
final class ActualizarOrdenRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cantidad_equipos_necesarios' => ['required', 'integer', 'min:1'],
            'tipo_aplicacion' => ['required', Rule::enum(TipoAplicacion::class)],
            'categoria_insumo_id' => ['required', 'integer', Rule::exists('ope_categorias_insumo', 'id')->whereNull('deleted_at')],
            'litros_ha' => ['nullable', 'numeric', 'gt:0'],
            'kilos_por_vuelo' => ['nullable', 'numeric', 'gt:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'emitida_por_contacto_id' => ['nullable', 'integer', Rule::exists('com_cliente_contactos', 'id')->whereNull('deleted_at')],
            'fecha_emision' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarCampoSegunCategoriaInsumo($validator);
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
            'cantidad_equipos_necesarios.required' => __('operaciones.ordenes.error_cantidad_equipos_requerida'),
            'tipo_aplicacion.required' => __('operaciones.ordenes.error_tipo_aplicacion_requerido'),
            'fecha_emision.required' => __('operaciones.ordenes.error_fecha_emision_requerida'),
            'categoria_insumo_id.required' => __('operaciones.ordenes.error_categoria_insumo_requerida'),
            'categoria_insumo_id.exists' => __('operaciones.ordenes.error_categoria_insumo_invalida'),
            'emitida_por_contacto_id.exists' => __('operaciones.ordenes.error_contacto_invalido'),
        ];
    }
}
