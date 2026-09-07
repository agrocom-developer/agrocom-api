<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Aplicacion\GuardarRol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un rol (`POST /panel/roles`, `PUT /panel/roles/{rol}`).
 *
 * `name` es la identidad del rol en la base y viaja a los códigos y a las
 * claves de idioma (`seguridad.rol.meta.<name>.nombre`), así que se valida
 * con la misma forma que ya tienen los cinco sembrados: minúsculas, dígitos y
 * guion bajo. Un rol llamado "Jefe de Campo" rompería esa correspondencia en
 * silencio.
 *
 * La unicidad se valida acá para dar el mensaje en el campo, pero NO es la
 * garantía: esa es el índice único de `sec_role.name`, y
 * {@see GuardarRol} traduce su violación a
 * `RolDuplicado` para la carrera entre dos altas simultáneas. `withoutTrashed()`
 * NO se usa a propósito: el índice es UNIQUE plano, así que un rol dado de
 * baja tampoco libera su nombre y la validación tiene que decirlo antes, no
 * después del error de la base.
 *
 * `autorizacion` la resuelve el controlador contra el ROL ACTIVO
 * (`AutorizacionPanelWeb`), no este request: mismo criterio que el resto del
 * panel.
 */
final class GuardarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rolEditado = $this->route('rol');

        return [
            'name' => [
                'required',
                'string',
                'max:30',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('sec_role', 'name')->ignore($rolEditado),
            ],
            'description' => ['required', 'string', 'max:150'],
            // Checkbox: ausente = desactivado. `boolean` sin `required`
            // porque un checkbox sin marcar no se envía.
            'state' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.regex' => __('seguridad.roles.error_nombre_formato'),
            'name.unique' => __('seguridad.roles.error_nombre_duplicado'),
        ];
    }
}
