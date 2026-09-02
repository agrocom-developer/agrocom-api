<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Dominio\TipoContactoCliente;
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
 */
final class CrearClienteRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'razon_social' => ['required', 'string', 'max:200'],
            'nit' => ['nullable', 'string', 'max:20'],
            'contactos' => ['required', 'array', 'min:1'],
            'contactos.*.tipo' => ['required', Rule::enum(TipoContactoCliente::class)],
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
            'contactos.required' => 'Agregá al menos un contacto.',
            'contactos.min' => 'Agregá al menos un contacto.',
            'contactos.*.tipo.enum' => 'El tipo de contacto no es válido.',
        ];
    }
}
