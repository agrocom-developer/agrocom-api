<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/ofertas/{id}/contraofertar`. */
final class ContraofertarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'precio_ha' => ['required', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,4'],
            'nota' => ['nullable', 'string', 'max:500'],
            'justificacion_precio' => ['nullable', 'string', 'max:500'],
            'uuid_cliente' => ['nullable', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('negociacion.campos');
    }
}
