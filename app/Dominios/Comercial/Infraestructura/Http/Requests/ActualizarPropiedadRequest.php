<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Dominio\ColorPropiedad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/propiedades/{propiedad}` (ADR 0018; ubicación estructurada —
 * adenda 16/9/2026 a ADR 0018 punto 1). Mismo criterio que
 * `CrearPropiedadRequest` para el nombre (sin regla `unique`, ver su
 * docblock) y para la cadena geográfica (la consistencia real la valida el
 * caso de uso, no este Request).
 */
final class ActualizarPropiedadRequest extends FormRequest
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
            'hectareas' => ['nullable', 'numeric', 'gt:0'],
            'departamento_id' => [
                'nullable',
                'integer',
                Rule::exists('com_departamentos', 'id')->whereNull('deleted_at'),
            ],
            'provincia_id' => [
                'nullable',
                'integer',
                Rule::exists('com_provincias', 'id')->whereNull('deleted_at'),
            ],
            'municipio_id' => [
                'nullable',
                'integer',
                Rule::exists('com_municipios', 'id')->whereNull('deleted_at'),
            ],
            'localidad' => ['nullable', 'string', 'max:150'],
            'color' => ['nullable', 'string', Rule::in(ColorPropiedad::valores())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Seleccioná un cliente.',
            'cliente_id.exists' => 'El cliente seleccionado no es válido.',
            'hectareas.gt' => 'Las hectáreas tienen que ser mayores a cero.',
            'departamento_id.exists' => 'El departamento seleccionado no es válido.',
            'provincia_id.exists' => 'La provincia seleccionada no es válida.',
            'municipio_id.exists' => 'El municipio seleccionado no es válido.',
            'color.in' => 'Elegí un color de la paleta disponible.',
        ];
    }
}
