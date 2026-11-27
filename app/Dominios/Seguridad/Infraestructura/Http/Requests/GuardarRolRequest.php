<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Aplicacion\GuardarRol;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta y edición de un rol (`POST /panel/roles`, `PUT /panel/roles/{rol}`).
 *
 * `name` es el nombre con el que se ve el rol en el panel («Supervisor de
 * campo»): letras, dígitos, espacios, guion y guion bajo, entre 3 y 40
 * caracteres. Los roles sembrados (`administrador`, `operador`) conservan su
 * nombre en minúsculas, y su texto legible sale de `seguridad.rol.meta.*`.
 *
 * La unicidad se valida acá para dar el mensaje en el campo, sin distinguir
 * mayúsculas y contando también los roles eliminados (el índice único de
 * `sec_role.name` no libera el nombre al dar de baja). NO es la garantía: esa es
 * {@see GuardarRol}, que repite la comprobación y es quien se defiende de la
 * carrera entre dos altas simultáneas.
 *
 * `authorize` la resuelve el controlador contra el ROL ACTIVO
 * (`AutorizacionPanelWeb`), no este request: mismo criterio que el resto del panel.
 */
final class GuardarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Un checkbox marcado sin `value` viaja como `on`, que `boolean` no acepta: se
     * normaliza a true/false antes de validar (ausente = desactivado).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'state' => $this->has('state') && ! in_array($this->input('state'), ['0', 'false', '', false, 0], true),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var SecRole|null $rolEditado */
        $rolEditado = $this->route('rol');

        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:40',
                'regex:/^[\p{L}\p{N}][\p{L}\p{N} _-]*$/u',
                function (string $atributo, mixed $valor, Closure $falla) use ($rolEditado): void {
                    $existe = SecRole::withTrashed()
                        ->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $valor))])
                        ->when($rolEditado !== null, fn ($consulta) => $consulta->where('id', '!=', $rolEditado?->id))
                        ->exists();

                    if ($existe) {
                        $falla(__('seguridad.roles.error_nombre_duplicado'));
                    }
                },
            ],
            'description' => ['required', 'string', 'max:150'],
            // Checkbox: ausente = desactivado (ver prepareForValidation()).
            'state' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('seguridad.roles.error_nombre_requerido'),
            'name.min' => __('seguridad.roles.error_nombre_corto'),
            'name.max' => __('seguridad.roles.error_nombre_largo'),
            'name.regex' => __('seguridad.roles.error_nombre_formato'),
            'description.required' => __('seguridad.roles.error_descripcion_requerida'),
            'description.max' => __('seguridad.roles.error_descripcion_larga'),
        ];
    }
}
