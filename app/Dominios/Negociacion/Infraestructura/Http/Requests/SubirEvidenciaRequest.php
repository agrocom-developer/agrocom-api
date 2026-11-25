<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de `POST /api/solicitudes/{id}/evidencias`. */
final class SubirEvidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'uuid_cliente' => ['required', 'uuid'],
            'tipo' => ['required', Rule::in(['foto_avance', 'foto_final', 'foto_incidencia'])],
            'avance_uuid_cliente' => ['nullable', 'uuid'], 'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp'],
        ];
    }
}
