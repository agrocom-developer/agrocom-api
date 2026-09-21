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
 *
 * `remember` (opcional): la casilla "Recordarme" del login-form. `boolean`
 * acepta tanto el `true`/`false` del JSON que manda `pages/login.js` como el
 * `"1"` de un submit clásico de formulario; ausente equivale a no marcada.
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
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'username.required' => __('seguridad.login.error_username_requerido'),
            'password.required' => __('seguridad.login.error_password_requerido'),
        ];
    }
}
