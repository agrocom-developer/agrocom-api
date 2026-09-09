<?php

namespace App\Dominios\Campania\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/campanias` (ADR 0015 punto 1, tarea 69). La autorización
 * (permiso `campania.campania.crear`) se verifica en el controlador, contra
 * el rol activo — no acá, mismo criterio que el resto del panel.
 *
 * `cliente_id` es obligatorio (corregido el 8/9/2026): la campaña es del
 * cliente, no de Agrocom.
 *
 * `codigo` no lleva regla `unique` a propósito: el índice único real es
 * PARCIAL y por cliente (`cpn_campanias_cliente_codigo_unico`, solo entre
 * filas activas), y la regla `unique` de Laravel no lo replica sola sin
 * quedar frágil ante altas y bajas lógicas — la violación se atrapa en
 * `CrearCampania` y se traduce ahí (mismo criterio que `CrearCampoRequest`
 * con el nombre del campo).
 */
final class CrearCampaniaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('com_clientes', 'id')->whereNull('deleted_at')],
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['nullable', 'string', 'max:150'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cliente_id.required' => __('campania.campanias.error_cliente_requerido'),
            'cliente_id.exists' => __('campania.campanias.error_cliente_invalido'),
        ];
    }
}
