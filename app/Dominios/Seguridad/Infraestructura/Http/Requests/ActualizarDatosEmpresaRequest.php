<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/organizacion/empresa`. Nombre y rubro con que se presenta la
 * empresa en el panel, más contacto opcional — sin `type`/`plan`/nada del
 * resto de la pestaña "Organización", que sigue sin persistencia real.
 *
 * `logo`: `mimes:png,svg` en vez de la regla `image` de Laravel porque
 * `image` excluye SVG. `max:2048` = 2 MB (ADR 0019). `logo_eliminar` es el
 * checkbox de "Quitar" de `molecules/file-field` — llega como `"1"` solo si
 * está tildado, por eso `boolean` (acepta esa forma además de true/false).
 */
final class ActualizarDatosEmpresaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'rubro' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:png,svg', 'max:2048'],
            'logo_eliminar' => ['nullable', 'boolean'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'logo.mimes' => __('seguridad.organizacion.error_logo_tipo'),
            'logo.max' => __('seguridad.organizacion.error_logo_tamano'),
        ];
    }
}
