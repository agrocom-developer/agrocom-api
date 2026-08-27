<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Credenciales de login del panel: `username` + password, nunca correo
 * (memoria del proyecto). Sin regla de formato sobre `username` acá — la
 * valida el intento de autenticación contra `sec_user`, no esta request.
 */
class IniciarSesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
