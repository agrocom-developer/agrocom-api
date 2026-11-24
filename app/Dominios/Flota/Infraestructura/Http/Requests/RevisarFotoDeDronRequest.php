<?php

namespace App\Dominios\Flota\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de la revisión de una foto de dron en el panel: aprobar, o rechazar con motivo. */
final class RevisarFotoDeDronRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:aprobar,rechazar'],
            'motivo' => ['required_if:decision,rechazar', 'nullable', 'string', 'min:3', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['motivo' => __('proveedores.campos.motivo')];
    }
}
