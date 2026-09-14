<?php

namespace App\Dominios\Campania\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/campanias/{campania}` (ADR 0015 punto 1, tarea 69). Mismas
 * reglas que `CrearCampaniaRequest` — ver ese docblock para el porqué de que
 * `codigo` no lleve `unique` y de que `cliente_id` sea obligatorio.
 *
 * `nombre` en blanco NO autogenera ni vacía acá (HU-77, tarea 93): a
 * diferencia del alta, `ActualizarCampania` preserva el nombre existente
 * cuando llega `null` — ver su docblock.
 */
final class ActualizarCampaniaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('com_clientes', 'id')->whereNull('deleted_at')],
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['nullable', 'string', 'max:150'],
            'estacion' => ['required', Rule::in(['invierno', 'verano'])],
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
