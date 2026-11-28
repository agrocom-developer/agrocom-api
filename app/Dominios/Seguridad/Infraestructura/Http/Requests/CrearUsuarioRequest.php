<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/usuarios`: alta de una cuenta de panel (HU-45, tarea 39). Validación de
 * forma y feedback temprano — la guarda de negocio real (duplicado de
 * `username`/`email`, permiso de `administrador`, un solo rol del panel) vive en
 * `AsignarRolesUsuario`, que el controlador invoca después; esto es la segunda línea
 * de defensa, no la primera. Solo se crean cuentas de panel (`type = interno`): las
 * de app se registran por su app o las crea la oficina desde la ficha del cliente o
 * del proveedor (ADR 0046).
 */
final class CrearUsuarioRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['interno'])],
            'name' => ['required', 'string', 'max:150'],
            'username' => [
                'required',
                'string',
                'max:60',
                Rule::unique('sec_user', 'username')->whereNull('deleted_at'),
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:150',
                Rule::unique('sec_user', 'email')->where('tipo_cuenta', 'panel')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', 'min:8'],
            // Una cuenta de panel tiene un solo rol (ADR 0046, punto 4); el catálogo de roles
            // del panel es fijo y `AsignarRolesUsuario` revalida que sea un rol del panel.
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
            'type.required' => __('seguridad.usuarios.error_type_requerido'),
            'name.required' => __('seguridad.usuarios.error_name_requerido'),
            'username.required' => __('seguridad.usuarios.error_username_requerido'),
            'password.required' => __('seguridad.usuarios.error_password_requerido'),
            'username.unique' => __('seguridad.validacion.usuario_username_unico'),
            'email.unique' => __('seguridad.validacion.usuario_email_unico'),
            'roles.required' => __('seguridad.validacion.usuario_roles_requerido'),
            'roles.size' => __('seguridad.validacion.usuario_roles_un_solo_rol'),
            'roles.*.exists' => __('seguridad.validacion.usuario_rol_invalido'),
        ];
    }
}
