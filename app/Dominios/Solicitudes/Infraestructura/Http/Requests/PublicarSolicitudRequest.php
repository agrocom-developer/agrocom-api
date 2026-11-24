<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/solicitudes/{id}/publicar`: solo al republicar una `sin_proveedor` se puede ampliar la ventana. */
final class PublicarSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ventana_hasta' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('solicitudes.campos');
    }
}
