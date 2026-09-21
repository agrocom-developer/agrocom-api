<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Dominio\ColorPropiedad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/propiedades` (ADR 0018; ubicación estructurada — adenda
 * 16/9/2026 a ADR 0018 punto 1). La autorización (permiso
 * `comercial.propiedad.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que el resto del panel.
 *
 * `departamento_id`/`provincia_id`/`municipio_id` solo validan `exists`
 * contra el catálogo activo acá — la consistencia de la CADENA (la
 * provincia pertenece al departamento elegido, el municipio a la provincia)
 * es una guarda de negocio y vive en el caso de uso
 * (`ValidadorUbicacionGeografica`), mismo criterio que `CampaniaDeOtroCliente`
 * de ADR 0015.
 *
 * Latitud/longitud/geometría NO se validan acá: se movieron a
 * `GuardarUbicacionMapaPropiedadRequest`, pantalla aparte.
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
            'cliente_id.required' => __('comercial.contratos.error_cliente_requerido'),
            'cliente_id.exists' => __('comercial.contratos.error_cliente_invalido'),
            'nombre.required' => __('comercial.propiedades.error_nombre_requerido'),
            'hectareas.gt' => __('comercial.validacion.hectareas_mayor_a_cero'),
            'departamento_id.exists' => __('comercial.validacion.departamento_invalido'),
            'provincia_id.exists' => __('comercial.validacion.provincia_invalida'),
            'municipio_id.exists' => __('comercial.validacion.municipio_invalido'),
            'color.in' => __('comercial.validacion.color_invalido'),
        ];
    }
}
