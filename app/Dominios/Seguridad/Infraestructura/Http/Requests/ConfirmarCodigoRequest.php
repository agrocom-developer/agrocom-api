<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Confirmar un código con la sesión iniciada. */
final class ConfirmarCodigoRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:10'],
        ];
    }
}
