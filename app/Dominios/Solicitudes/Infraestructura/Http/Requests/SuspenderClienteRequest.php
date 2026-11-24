<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de la suspensión de un cliente desde el panel: el motivo es obligatorio. */
final class SuspenderClienteRequest extends FormRequest
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
            'motivo.required' => __('solicitudes.clientes.error_motivo_requerido'),
            'motivo.min' => __('solicitudes.clientes.error_motivo_corto'),
            'motivo.max' => __('solicitudes.clientes.error_motivo_largo'),
        ];
    }
}
