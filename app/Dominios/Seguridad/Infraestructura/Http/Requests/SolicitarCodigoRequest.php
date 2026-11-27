<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Pedir un código a la cuenta con sesión (cambiar la contraseña, verificar el teléfono): el canal lo elige la persona. */
final class SolicitarCodigoRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'canal' => ['required', 'string', Rule::in(['whatsapp', 'sms', 'correo'])],
        ];
    }
}
