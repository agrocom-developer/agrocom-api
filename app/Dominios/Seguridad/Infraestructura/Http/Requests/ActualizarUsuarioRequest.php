<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/usuarios/{usuario}` (HU-45, tarea 39). Mismas reglas que
 * `CrearUsuarioRequest`, salvo `password` (vacío = conserva el hash vigente,
 * ya lo soporta `AsignarRolesUsuario`) y el `unique` de `username`, que
 * ignora la propia cuenta.
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
            'password' => ['nullable', 'string', 'min:8'],
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
