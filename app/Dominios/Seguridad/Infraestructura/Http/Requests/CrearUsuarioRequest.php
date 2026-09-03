<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/usuarios` (HU-45, tarea 39). Validación de forma/feedback
 * temprano — la guarda de negocio real (duplicado de `username`/
 * `persona_id`, permiso `asignar_rol_dueno`) sigue viviendo en
 * `AsignarRolesUsuario`, que el controlador invoca después; esto es la
 * segunda línea de defensa, no la primera.
 *
 * `type` NO se valida ni se acepta acá a propósito: el controlador lo fija a
 * `TipoUsuario::Interno`, siempre — `Cliente` es del portal (HU-41), otro
 * flujo de alta.
 */
final class CrearUsuarioRequest extends FormRequest
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
                Rule::unique('sec_user', 'username')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', 'min:8'],
            'persona_id' => [
                'nullable',
                'integer',
                Rule::exists('per_personas', 'id')->whereNull('deleted_at'),
            ],
            'roles' => ['present', 'array'],
            'roles.*' => ['integer', Rule::exists('sec_role', 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'username.unique' => 'Ya existe una cuenta activa con ese username.',
            'persona_id.exists' => 'La persona seleccionada no es válida.',
            'roles.*.exists' => 'Uno de los roles seleccionados no es válido.',
        ];
    }
}
