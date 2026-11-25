<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use App\Dominios\Negociacion\Aplicacion\Mensajeria;
use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo (multipart) de `POST /api/solicitudes/{id}/mensajes/fotos`. */
final class EnviarFotoDeMensajeRequest extends FormRequest
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
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp'],
            'texto' => ['nullable', 'string', 'max:'.Mensajeria::LIMITE_PIE],
        ];
    }
}
