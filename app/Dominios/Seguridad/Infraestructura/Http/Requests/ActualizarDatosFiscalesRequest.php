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
}
