<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/usuarios` (HU-45, tarea 39; tarea 65 le agrega `type`).
 * Validación de forma/feedback temprano — la guarda de negocio real
 * (duplicado de `username`/`persona_id`, permisos de rol dueño y de portal,
 * contrato vigente) sigue viviendo en `AsignarRolesUsuario`/
 * `CrearCuentaPortal`, que el controlador invoca después; esto es la
 * segunda línea de defensa, no la primera.
 *
 * Un solo formulario para los dos tipos (interno/cliente): `persona_id` y
 * `roles` son del camino interno, `contrato_id` es del camino portal.
 * `present_if`/`prohibited_if`/`required_if` sobre `type` (el propio campo
 * del payload) hacen que mezclar campos de un camino con el tipo del otro
 * (p. ej. `type=cliente` + `persona_id`) sea un 422, nunca un dato ignorado
 * en silencio. `roles` usa `present_if`, no `required_if`: una cuenta
 * interna sin ningún rol asignado es un estado válido (el array puede
 * llegar vacío), lo que no se permite es OMITIR la clave.
 */
final class CrearUsuarioRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['interno', 'cliente'])],
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
                Rule::unique('sec_user', 'email')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', 'min:8'],
            'persona_id' => [
                'prohibited_if:type,cliente',
                'nullable',
                'integer',
                Rule::exists('per_personas', 'id')->whereNull('deleted_at'),
            ],
            'contrato_id' => [
                'required_if:type,cliente',
                'prohibited_if:type,interno',
                'integer',
                Rule::exists('com_contratos', 'id')->whereNull('deleted_at')->where('estado', 'vigente'),
            ],
            'roles' => [
                'present_if:type,interno',
                'prohibited_if:type,cliente',
                'array',
            ],
            'roles.*' => ['integer', Rule::exists('sec_role', 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'username.unique' => 'Ya existe una cuenta activa con ese username.',
            'email.unique' => 'Ya existe una cuenta activa con ese correo.',
            'persona_id.exists' => 'La persona seleccionada no es válida.',
            'persona_id.prohibited_if' => 'Una cuenta de portal no tiene persona asociada.',
            'contrato_id.required_if' => 'Elegí el contrato de la cuenta de portal.',
            'contrato_id.prohibited_if' => 'Una cuenta interna no tiene contrato asociado.',
            'contrato_id.exists' => 'El contrato elegido no existe o no está vigente.',
            'roles.present_if' => 'Falta el campo de roles.',
            'roles.prohibited_if' => 'Una cuenta de portal no tiene roles.',
            'roles.*.exists' => 'Uno de los roles seleccionados no es válido.',
        ];
    }
}
