<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `POST /api/cuenta/telefono/verificacion`: el teléfono se verifica por WhatsApp o SMS, nunca por correo. */
final class SolicitarVerificacionDeTelefonoRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'canal' => ['required', 'string', Rule::in(['whatsapp', 'sms'])],
        ];
    }
}
