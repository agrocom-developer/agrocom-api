<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/bases` (HU-26, tarea 37). La autorización (permiso
 * `personal.base.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que el resto del panel.
 */
final class CrearBaseRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'ubicacion' => ['nullable', 'string', 'max:200'],
            'latitud' => ['nullable', 'required_with:longitud', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'required_with:latitud', 'numeric', 'between:-180,180'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.required' => __('personal.bases.error_nombre_requerido'),
            'latitud.required_with' => __('personal.bases.error_coordenada_incompleta'),
            'longitud.required_with' => __('personal.bases.error_coordenada_incompleta'),
        ];
    }
}
