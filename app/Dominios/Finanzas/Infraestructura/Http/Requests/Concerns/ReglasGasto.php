<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Reglas de forma de un gasto, compartidas por `CrearGastoRequest` y
 * `ActualizarGastoRequest` (tarea 134) — la edición no cambia ni un campo
 * respecto del alta, mismo criterio de "sacarlo del actual, no duplicarlo".
 */
trait ReglasGasto
{
    /** @return array<string, mixed> */
    private function reglasGasto(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'rubro_id' => ['required', 'integer', Rule::exists('fin_rubros', 'id')->whereNull('deleted_at')],
            'subrubro_id' => ['nullable', 'integer', Rule::exists('fin_subrubros', 'id')->whereNull('deleted_at')],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'precio_unitario' => ['required', 'numeric', 'gt:0'],
            'equipo_trabajo_id' => ['nullable', 'integer', Rule::exists('per_equipos_trabajo', 'id')->whereNull('deleted_at')],
            'base_id' => ['nullable', 'integer', Rule::exists('per_bases', 'id')->whereNull('deleted_at')],
            'trabajo_id' => ['nullable', 'integer', Rule::exists('ope_trabajos', 'id')->whereNull('deleted_at')],
            'campania_id' => ['nullable', 'integer', Rule::exists('cpn_campanias', 'id')->whereNull('deleted_at')],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    private function mensajesGasto(): array
    {
        return [
            'rubro_id.required' => __('finanzas.gastos.error_rubro_requerido'),
            'rubro_id.exists' => __('finanzas.gastos.error_rubro_invalido'),
            'comprobante.mimes' => __('finanzas.gastos.error_comprobante_tipo'),
            'comprobante.max' => __('finanzas.gastos.error_comprobante_tamano'),
            'fecha.required' => __('finanzas.gastos.error_fecha_requerida'),
            'cantidad.required' => __('finanzas.gastos.error_cantidad_requerida'),
            'precio_unitario.required' => __('finanzas.gastos.error_precio_unitario_requerido'),
        ];
    }
}
