<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Reglas de forma de la cabecera de una rendición, compartidas por
 * `CrearRendicionRequest` y `ActualizarRendicionRequest` (tarea 134) — la
 * edición solo toca cabecera (`base_id`/`jefe_campo_id`/`fecha`/`descripcion`),
 * mismos campos y mismas reglas que el alta.
 */
trait ReglasRendicion
{
    /** @return array<string, mixed> */
    private function reglasRendicion(): array
    {
        return [
            'base_id' => ['required', 'integer', Rule::exists('per_bases', 'id')->whereNull('deleted_at')],
            'jefe_campo_id' => ['required', 'integer', Rule::exists('per_personas', 'id')->whereNull('deleted_at')],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    private function mensajesRendicion(): array
    {
        return [
            'base_id.required' => __('finanzas.rendiciones.error_base_requerida'),
            'base_id.exists' => __('finanzas.rendiciones.error_base_invalida'),
            'jefe_campo_id.required' => __('finanzas.rendiciones.error_jefe_campo_requerido'),
            'jefe_campo_id.exists' => __('finanzas.rendiciones.error_jefe_campo_invalido'),
            'fecha.required' => __('finanzas.rendiciones.error_fecha_requerida'),
        ];
    }
}
