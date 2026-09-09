<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Credenciales de login del panel: `username` + password, nunca correo
 * (memoria del proyecto). Sin regla de formato sobre `username` acá — la
 * valida el intento de autenticación contra `sec_user`, no esta request.
 *
 * `zona_horaria` (tarea 63, opcional): lo que declara el navegador
 * (`Intl.DateTimeFormat().resolvedOptions().timeZone`) en un campo oculto del
 * login-form. Sin regla de IANA acá a propósito — el login nunca falla por
 * esto; quien valida y descarta un valor no reconocido es
 * `FijarZonaHorariaUsuario`, después de autenticar.
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
            'zona_horaria' => ['nullable', 'string'],
        ];
    }
}
