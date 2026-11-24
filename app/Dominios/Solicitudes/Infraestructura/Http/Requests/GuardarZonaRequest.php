<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo del alta y la edición de una zona: nombre único, región del catálogo y coordenadas válidas. */
final class GuardarZonaRequest extends FormRequest
{
    /** Regiones que el mapa y los avisos conocen (las mismas de las preferencias de aviso). */
    public const REGIONES = ['cercado', 'norte_integrado', 'chiquitania', 'valles', 'chaco'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $zona = $this->route('zona');

        return [
            'nombre' => [
                'required', 'string', 'max:80',
                Rule::unique('sol_zonas', 'nombre')->whereNull('deleted_at')->ignore($zona),
            ],
            'macrorregion' => ['required', Rule::in(self::REGIONES)],
            'latitud' => ['required', 'numeric', 'between:-90,90', 'regex:/^-?\d{1,2}(\.\d{1,7})?$/'],
            'longitud' => ['required', 'numeric', 'between:-180,180', 'regex:/^-?\d{1,3}(\.\d{1,7})?$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.required' => __('solicitudes.zonas.error_nombre_requerido'),
            'nombre.max' => __('solicitudes.zonas.error_nombre_largo'),
            'nombre.unique' => __('solicitudes.zonas.error_nombre_duplicado'),
            'macrorregion.required' => __('solicitudes.zonas.error_region_requerida'),
            'macrorregion.in' => __('solicitudes.zonas.error_region_invalida'),
            'latitud.required' => __('solicitudes.zonas.error_latitud'),
            'latitud.numeric' => __('solicitudes.zonas.error_latitud'),
            'latitud.between' => __('solicitudes.zonas.error_latitud'),
            'latitud.regex' => __('solicitudes.zonas.error_latitud'),
            'longitud.required' => __('solicitudes.zonas.error_longitud'),
            'longitud.numeric' => __('solicitudes.zonas.error_longitud'),
            'longitud.between' => __('solicitudes.zonas.error_longitud'),
            'longitud.regex' => __('solicitudes.zonas.error_longitud'),
        ];
    }
}
