<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de la revisión de un documento: aprobar, o rechazar con motivo (ADR 0032, punto 5). */
final class RevisarDocumentoRequest extends FormRequest
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
            'motivo' => ['required_if:decision,rechazar', 'nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['motivo' => __('proveedores.campos.motivo')];
    }
}
