<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de la intervención del panel: el motivo es obligatorio y lo ven las dos partes. */
final class CerrarNegociacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo.required' => __('negociacion.panel.error_motivo_requerido'),
            'motivo.min' => __('negociacion.panel.error_motivo_corto'),
            'motivo.max' => __('negociacion.panel.error_motivo_largo'),
        ];
    }
}
