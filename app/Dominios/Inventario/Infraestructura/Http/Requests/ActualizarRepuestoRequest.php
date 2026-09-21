<?php

namespace App\Dominios\Inventario\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /panel/repuestos/{repuesto}` (HU-36, tarea 52). Mismas reglas que
 * `CrearRepuestoRequest` — ver ese docblock.
 */
final class ActualizarRepuestoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:60'],
            'descripcion' => ['required', 'string', 'max:255'],
            'unidad' => ['required', 'string', 'max:30'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo.required' => __('inventario.repuestos.error_codigo_requerido'),
            'descripcion.required' => __('inventario.repuestos.error_descripcion_requerida'),
            'unidad.required' => __('inventario.repuestos.error_unidad_requerida'),
        ];
    }
}
