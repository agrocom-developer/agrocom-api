<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/propiedades` (ADR 0018). La autorización (permiso
 * `comercial.propiedad.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que el resto del panel.
 *
 * El nombre de la propiedad no lleva regla `unique` a propósito: el índice
 * único real es PARCIAL (`com_propiedades_nombre_unico`, solo entre filas
 * activas), y la regla `unique` de Laravel no lo replica sola sin quedar
 * frágil ante altas y bajas lógicas — la violación se atrapa en
 * `CrearPropiedad` y se traduce ahí (mismo criterio que `CrearCampoRequest`
 * con el nombre del campo).
 */
final class CrearPropiedadRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cliente_id' => [
                'required',
                'integer',
                Rule::exists('com_clientes', 'id')->whereNull('deleted_at'),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Seleccioná un cliente.',
            'cliente_id.exists' => 'El cliente seleccionado no es válido.',
        ];
    }
}
