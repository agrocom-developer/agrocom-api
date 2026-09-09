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
            'email.unique' => 'Ya existe una cuenta activa con ese correo.',
            'password_actual.required_with' => 'Ingresá tu contraseña actual para poder cambiarla.',
            'password.confirmed' => 'La confirmación no coincide con la contraseña nueva.',
        ];
    }
}
