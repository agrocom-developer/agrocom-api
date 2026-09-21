<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/organizacion/facturacion` (tarea 78, HU-55). Los cinco campos
 * fiscales con que se emite factura — sin `type`/`plan`/nada del mockup de
 * organización, que sigue sin persistencia real.
 */
final class ActualizarDatosFiscalesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'razon_social_fiscal' => ['required', 'string', 'max:255'],
            'nit' => ['required', 'string', 'max:50'],
            'domicilio_fiscal' => ['required', 'string', 'max:255'],
            'actividad_economica' => ['required', 'string', 'max:255'],
            'leyenda_pie' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'razon_social_fiscal.required' => __('seguridad.organizacion.error_razon_social_fiscal_requerida'),
            'nit.required' => __('seguridad.organizacion.error_nit_requerido'),
            'domicilio_fiscal.required' => __('seguridad.organizacion.error_domicilio_fiscal_requerido'),
            'actividad_economica.required' => __('seguridad.organizacion.error_actividad_economica_requerida'),
        ];
    }
}
