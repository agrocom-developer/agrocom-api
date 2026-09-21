<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Dominio\TipoContactoCliente;
use App\Dominios\Comercial\Dominio\TipoPersonaCliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/clientes` (HU-22, tarea 33). La autorización (permiso
 * `comercial.cliente.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que el resto del panel.
 *
 * El NIT no lleva regla `unique` a propósito: el índice único real es
 * PARCIAL (`com_clientes_nit_unico`, solo entre clientes activos), y la
 * regla `unique` de Laravel no lo replica sola sin quedar frágil ante altas y
 * bajas lógicas — la violación se atrapa en `CrearCliente` y se traduce ahí.
 *
 * `logo` (HU-75, tarea 91): mismos límites que `ActualizarDatosEmpresaRequest`
 * (ADR 0019) — `mimes:png,svg` en vez de `image` (que excluye SVG), `max:2048`
 * = 2 MB. Sin `logo_eliminar`: en alta no hay logo previo que eliminar.
 */
final class CrearClienteRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'razon_social' => ['required', 'string', 'max:200'],
            'nombre_comercial' => ['nullable', 'string', 'max:200'],
            'nit' => ['nullable', 'string', 'max:20'],
            'tipo_persona' => ['required', Rule::enum(TipoPersonaCliente::class)],
            'ubicacion_oficina' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:png,svg,jpg,jpeg,webp,gif', 'max:20480'],
            'contactos' => ['required', 'array', 'min:1'],
            'contactos.*.tipo' => ['required', Rule::enum(TipoContactoCliente::class)],
            'contactos.*.tipo_otro' => ['nullable', 'string', 'max:100', 'required_if:contactos.*.tipo,otro'],
            'contactos.*.nombre' => ['required', 'string', 'max:150'],
            'contactos.*.telefono' => ['nullable', 'string', 'max:30'],
            'contactos.*.email' => ['nullable', 'email', 'max:150'],
            'contactos.*.observaciones' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'razon_social.required' => __('comercial.clientes.error_razon_social_requerida'),
            'tipo_persona.required' => __('comercial.validacion.tipo_persona_requerido'),
            'tipo_persona.enum' => __('comercial.validacion.tipo_persona_invalido'),
            'logo.mimes' => __('comercial.clientes.error_logo_tipo'),
            'logo.max' => __('comercial.clientes.error_logo_tamano'),
            'logo.uploaded' => __('comercial.clientes.error_logo_subida'),
            'contactos.required' => __('comercial.validacion.contactos_requeridos'),
            'contactos.min' => __('comercial.validacion.contactos_requeridos'),
            'contactos.*.tipo.required' => __('comercial.clientes.error_contacto_tipo_requerido'),
            'contactos.*.tipo.enum' => __('comercial.validacion.contacto_tipo_invalido'),
            'contactos.*.tipo_otro.required_if' => __('comercial.clientes.error_contacto_tipo_otro'),
            'contactos.*.nombre.required' => __('comercial.clientes.error_contacto_nombre_requerido'),
        ];
    }
}
