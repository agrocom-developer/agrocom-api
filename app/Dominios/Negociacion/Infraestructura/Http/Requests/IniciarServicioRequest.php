<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/solicitudes/{id}/inicio`. */
final class IniciarServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['uuid_cliente' => ['required', 'uuid'], 'secuencia' => ['nullable', 'integer', 'min:0'], 'registrado_en' => ['nullable', 'date']];
    }
}
