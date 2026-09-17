<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /portal/perfil` (tarea 66): mismo mecanismo que
 * {@see ActualizarPerfilRequest}, para el guard `cliente` — separada porque
 * el `ignore()` del email tiene que resolver el usuario de ESTE guard, no
 * el del panel.
 */
final class ActualizarPerfilPortalRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:150',
                Rule::unique('sec_user', 'email')->whereNull('deleted_at')->ignore($this->user('cliente')),
            ],
            'password_actual' => ['required_with:password', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('seguridad.perfil.error_name_requerido'),
            'email.unique' => __('seguridad.validacion.usuario_email_unico'),
            'password_actual.required_with' => __('seguridad.validacion.perfil_password_actual_requerida'),
            'password.confirmed' => __('seguridad.validacion.perfil_password_confirmacion'),
        ];
    }
}
