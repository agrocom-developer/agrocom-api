<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/perfil` (tarea 66): autoservicio del guard `interno`. El
 * cambio de contraseña es opcional — si no viene `password`, `password_actual`
 * tampoco se exige (la guarda real de que sí matchee el hash vigente es
 * `ActualizarPerfilPropio`, no esta clase: acá solo se valida forma).
 */
final class ActualizarPerfilRequest extends FormRequest
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
                Rule::unique('sec_user', 'email')->whereNull('deleted_at')->ignore($this->user('interno')),
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
