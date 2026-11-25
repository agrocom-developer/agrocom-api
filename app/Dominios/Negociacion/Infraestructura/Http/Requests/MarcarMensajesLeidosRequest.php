<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/solicitudes/{id}/mensajes/leidos`. */
final class MarcarMensajesLeidosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['hasta' => ['required', 'integer', 'min:0']];
    }
}
