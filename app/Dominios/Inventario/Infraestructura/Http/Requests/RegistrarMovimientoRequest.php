<?php

namespace App\Dominios\Inventario\Infraestructura\Http\Requests;

use App\Dominios\Inventario\Dominio\SentidoAjusteInventario;
use App\Dominios\Inventario\Dominio\TipoMovimientoInventario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/stock/movimientos` (HU-36, tarea 52). La autorización
 * (permiso `inventario.movimiento.crear`) se verifica en el controlador,
 * contra el rol activo — no acá, mismo criterio que el resto del panel.
 *
 * Reglas condicionales por `tipo` (los cuatro comparten un único formulario,
 * ver `_formulario.blade.php`):
 * - `base_destino_id`: obligatoria SOLO en `traslado`, y tiene que ser
 *   distinta de `base_id` (un traslado a la misma base no es un movimiento).
 * - `sentido`: obligatorio SOLO en `ajuste` — es el único tipo donde el signo
 *   no lo determina `tipo` por sí solo (ver `SentidoAjusteInventario`).
 * - `costo_unitario`: obligatorio SOLO en `compra` — es lo único que
 *   sobrescribe `inv_repuestos.costo_unitario` (ver `RegistrarMovimientoStock`).
 * - `motivo`: obligatorio en `ajuste` y `traslado` — un ajuste o un traslado
 *   sin motivo es imposible de auditar después; `compra`/`salida` no lo
 *   necesitan (el propio movimiento ya es la justificación).
 *
 * `orden_mantenimiento_id` no se expone en este formulario: la tarea 53
 * (HU-37, todavía sin implementar) es la que va a completarlo desde el flujo
 * de cierre de una orden de mantenimiento, no un alta manual del panel.
 */
final class RegistrarMovimientoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoMovimientoInventario::class)],
            'repuesto_id' => ['required', 'integer', Rule::exists('inv_repuestos', 'id')->whereNull('deleted_at')],
            'base_id' => ['required', 'integer', Rule::exists('per_bases', 'id')->whereNull('deleted_at')],
            'base_destino_id' => [
                Rule::requiredIf($this->input('tipo') === TipoMovimientoInventario::Traslado->value),
                'nullable',
                'integer',
                'different:base_id',
                Rule::exists('per_bases', 'id')->whereNull('deleted_at'),
            ],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'sentido' => [
                Rule::requiredIf($this->input('tipo') === TipoMovimientoInventario::Ajuste->value),
                'nullable',
                Rule::enum(SentidoAjusteInventario::class),
            ],
            'costo_unitario' => [
                Rule::requiredIf($this->input('tipo') === TipoMovimientoInventario::Compra->value),
                'nullable',
                'numeric',
                'min:0',
            ],
            'motivo' => [
                Rule::requiredIf(in_array($this->input('tipo'), [TipoMovimientoInventario::Ajuste->value, TipoMovimientoInventario::Traslado->value], true)),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tipo.required' => __('inventario.stock.error_tipo_requerido'),
            'repuesto_id.required' => __('inventario.stock.error_repuesto_requerido'),
            'base_id.required' => __('inventario.stock.error_base_requerida'),
            'cantidad.required' => __('inventario.stock.error_cantidad_requerida'),
            'base_destino_id.different' => __('inventario.validacion.base_destino_distinta'),
            'base_destino_id.required' => __('inventario.validacion.traslado_base_destino_requerida'),
            'sentido.required' => __('inventario.validacion.ajuste_sentido_requerido'),
            'costo_unitario.required' => __('inventario.validacion.compra_costo_unitario_requerido'),
            'motivo.required' => __('inventario.validacion.motivo_requerido'),
        ];
    }
}
