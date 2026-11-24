<?php

namespace App\Dominios\Asignacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/ofertas/{id}/rechazar`: el motivo es obligatorio (especificación §6, punto 9). */
final class RechazarOfertaRequest extends FormRequest
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
    public function attributes(): array
    {
        return __('asignacion.campos');
    }
}
