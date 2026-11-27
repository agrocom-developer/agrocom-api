<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `POST /api/auth/recuperar` (ADR 0046, punto 27): quién es, desde qué app escribe y por dónde quiere el código. */
final class SolicitarRecuperacionRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'identificador' => ['required', 'string', 'max:150'],
            'app' => ['required', 'string', Rule::in(['cliente', 'proveedor', 'piloto'])],
            'canal' => ['required', 'string', Rule::in(['whatsapp', 'sms', 'correo'])],
        ];
    }
}
