<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use App\Dominios\Negociacion\Aplicacion\Mensajeria;
use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/solicitudes/{id}/mensajes`. */
final class EnviarMensajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['uuid_cliente' => ['required', 'uuid'], 'texto' => ['required', 'string', 'max:'.Mensajeria::LIMITE_TEXTO]];
    }
}
