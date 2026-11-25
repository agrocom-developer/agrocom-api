<?php

namespace App\Dominios\Notificaciones\Infraestructura\Http\Requests;

use App\Dominios\Notificaciones\Aplicacion\DispositivosPush;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de `POST /api/dispositivos-push`. */
final class RegistrarDispositivoPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
            'plataforma' => ['required', Rule::in(DispositivosPush::PLATAFORMAS)],
        ];
    }
}
