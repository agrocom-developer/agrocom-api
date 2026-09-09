<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/usuarios/{usuario}` (HU-45, tarea 39; tarea 65 le agrega el
 * camino portal). Mismas reglas que `CrearUsuarioRequest`, salvo `password`
 * (vacío = conserva el hash vigente) y el `unique` de `username`, que
 * ignora la propia cuenta.
 *
 * `type` NO se acepta en la edición a propósito (CLAUDE.md, tarea 65: "una
 * cuenta no muta de interna a cliente ni al revés — es otra cuenta"):
 * `prepareForValidation()` PISA cualquier `type` que venga en el payload con
 * el del usuario ya persistido (route binding) — un `PUT` armado a mano con
 * otro `type` no tiene ningún efecto, ni siquiera sobre las reglas
 * condicionales de abajo (`present_if`/`prohibited_if`/`required_if` leen
 * ese mismo campo ya pisado).
 */
final class ActualizarUsuarioRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        /** @var SecUser $usuario */
        $usuario = $this->route('usuario');

        $this->merge(['type' => $usuario->type->value]);
    }

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
                Rule::unique('sec_user', 'email')->whereNull('deleted_at')->ignore($this->route('usuario')),
            ],
            'password' => ['nullable', 'string', 'min:8'],
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
