<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/usuarios/{usuario}` (HU-45, tarea 39). Mismas reglas que
 * `CrearUsuarioRequest`, salvo `password` (vacío = conserva el hash vigente) y el
 * `unique` de `username`/`email`, que ignoran la propia cuenta. El tipo de cuenta no
 * se acepta: una cuenta de panel no cambia de tipo (ADR 0046).
 */
final class ActualizarUsuarioRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'username' => [
                'required',
                'string',
                'max:60',
                Rule::unique('sec_user', 'username')->whereNull('deleted_at')->ignore($this->route('usuario')),
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:150',
                Rule::unique('sec_user', 'email')->where('tipo_cuenta', 'panel')->whereNull('deleted_at')->ignore($this->route('usuario')),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            // Una cuenta de panel tiene un solo rol (ADR 0046, punto 4); el catálogo de roles
            // del panel es fijo y `AsignarRolesUsuario` revalida que sea administrador u operador.
            'roles' => [
                'required',
                'array',
                'size:1',
            ],
            'roles.*' => ['integer', Rule::exists('sec_role', 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('seguridad.usuarios.error_name_requerido'),
            'username.required' => __('seguridad.usuarios.error_username_requerido'),
            'username.unique' => __('seguridad.validacion.usuario_username_unico'),
            'email.unique' => __('seguridad.validacion.usuario_email_unico'),
            'roles.required' => __('seguridad.validacion.usuario_roles_requerido'),
            'roles.size' => __('seguridad.validacion.usuario_roles_un_solo_rol'),
            'roles.*.exists' => __('seguridad.validacion.usuario_rol_invalido'),
        ];
    }
}
