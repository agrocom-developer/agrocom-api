<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Reglas de forma de una carga de combustible, compartidas por
 * `CrearCombustibleRequest` y `ActualizarCombustibleRequest` (tarea 134) —
 * la edición no cambia ni un campo respecto del alta.
 */
trait ReglasCombustible
{
    /** @return array<string, mixed> */
    private function reglasCombustible(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'base_id' => ['required', 'integer', Rule::exists('per_bases', 'id')->whereNull('deleted_at')],
            'equipo_trabajo_id' => ['required', 'integer', Rule::exists('per_equipos_trabajo', 'id')->whereNull('deleted_at')],
            'campania_id' => ['nullable', 'integer', Rule::exists('cpn_campanias', 'id')->whereNull('deleted_at')],
            'recurso' => ['required', 'regex:/^(dron|vehiculo|generador):\d+$/'],
            'litros' => ['required', 'numeric', 'gt:0'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'descripcion' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    private function mensajesCombustible(): array
    {
        return [
            'base_id.required' => __('finanzas.combustible.error_base_requerida'),
            'base_id.exists' => __('finanzas.combustible.error_base_invalida'),
            'equipo_trabajo_id.required' => __('finanzas.combustible.error_equipo_requerido'),
            'equipo_trabajo_id.exists' => __('finanzas.combustible.error_equipo_invalido'),
            'recurso.required' => __('finanzas.combustible.error_recurso_requerido'),
            'recurso.regex' => __('finanzas.combustible.error_recurso_invalido'),
            'fecha.required' => __('finanzas.combustible.error_fecha_requerida'),
            'litros.required' => __('finanzas.combustible.error_litros_requerido'),
            'monto.required' => __('finanzas.combustible.error_monto_requerido'),
        ];
    }
}
