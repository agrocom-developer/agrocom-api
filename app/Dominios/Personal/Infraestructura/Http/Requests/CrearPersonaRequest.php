<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use App\Dominios\Personal\Dominio\RolOperativoPersona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/personas` (HU-26, tarea 37). La autorización (permiso
 * `personal.persona.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que el resto del panel.
 *
 * `base_id` es opcional (FK nullable) — una persona sin base asignada es un
 * caso válido, no un dato incompleto. Cuando viene, tiene que apuntar a una
 * base VIVA (`whereNull('deleted_at')`), mismo criterio que `cliente_id` en
 * `CrearCampoRequest`.
 */
final class CrearPersonaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'rol' => ['required', Rule::enum(RolOperativoPersona::class)],
            'base_id' => [
                'nullable',
                'integer',
                Rule::exists('per_bases', 'id')->whereNull('deleted_at'),
            ],
            'tarifa_ha' => ['nullable', 'numeric', 'min:0'],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.required' => __('personal.personas.error_nombre_requerido'),
            'rol.required' => __('personal.personas.error_rol_requerido'),
            'base_id.exists' => __('personal.validacion.base_invalida'),
        ];
    }
}
