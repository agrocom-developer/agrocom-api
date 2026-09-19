<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Dominio\TipoAplicacion;
use App\Dominios\Operaciones\Dominio\TipoInsumo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/ordenes` (HU-25 reforma Entrega 1, 18/9/2026): alta de orden
 * de aplicación. Ahora una orden cubre TODOS los lotes del contrato (no se
 * elige cada uno), con aplicación automática correlativa — el formulario ya
 * no pide `lotes[]` ni `nro_aplicacion`.
 *
 * La autorización (permiso `operaciones.orden.crear`) se verifica en el
 * controlador, contra el rol activo — no acá.
 *
 * Los rangos numéricos replican los `CHECK` de la migración — así el usuario
 * ve un error de validación de Laravel, nunca el `QueryException` crudo de
 * Postgres.
 *
 * `contrato_id`/`emitida_por_contacto_id` se validan por `exists:` contra la
 * tabla física (ADR 0003, regla 3).
 *
 * `tipo_aplicacion` es `required` en el formulario porque el usuario elige a
 * propósito, no por omisión.
 *
 * `categoria_insumo_id` es `required` — toda orden nueva del panel la elige
 * a propósito. `litros_ha`/`kilos_por_vuelo` son `nullable` porque cuál hace
 * falta depende del `tipo_insumo` de la categoría — esa exigencia cruzada vive
 * en `withValidator()`.
 */
final class CrearOrdenRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contrato_id' => ['required', 'integer', Rule::exists('com_contratos', 'id')->whereNull('deleted_at')],
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

    /**
     * HU-79 (tarea 110): el campo que la orden REALMENTE exige depende del
     * `tipo_insumo` de la categoría elegida — sólido pide `kilos_por_vuelo`,
     * líquido pide `litros_ha`. Cruza `ope_categorias_insumo` (no es un
     * `Rule::requiredIf` estático porque depende de un valor de OTRA tabla,
     * resuelto recién acá) — mismo criterio que la validación de hectáreas
     * por lote, unas líneas más abajo. Si `categoria_insumo_id` ya falló su
     * propio `exists`, no hay categoría que resolver: no se agrega un
     * segundo error encima del que ya puso `rules()`.
     */
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
            'cantidad_equipos_necesarios.required' => __('operaciones.ordenes.error_cantidad_equipos_requerida'),
            'tipo_aplicacion.required' => __('operaciones.ordenes.error_tipo_aplicacion_requerido'),
            'fecha_emision.required' => __('operaciones.ordenes.error_fecha_emision_requerida'),
            'categoria_insumo_id.required' => __('operaciones.ordenes.error_categoria_insumo_requerida'),
            'categoria_insumo_id.exists' => __('operaciones.ordenes.error_categoria_insumo_invalida'),
            'emitida_por_contacto_id.exists' => __('operaciones.ordenes.error_contacto_invalido'),
        ];
    }
}
