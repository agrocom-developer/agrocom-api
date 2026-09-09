<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Dominio\TipoContactoCliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/clientes/{cliente}` (HU-22, tarea 33). Mismo criterio que
 * `CrearClienteRequest` para el NIT (sin regla `unique`, ver su docblock).
 *
 * `contactos.*.id`, cuando viene, tiene que pertenecer AL PROPIO cliente que
 * se está editando — nunca a otro (mismo espíritu que la regla del portal del
 * cliente, invariante 5 de CLAUDE.md, aplicada acá al panel interno).
 */
final class ActualizarClienteRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Cliente|null $cliente */
        $cliente = $this->route('cliente');
        $clienteId = $cliente?->id;

        return [
            'razon_social' => ['required', 'string', 'max:200'],
            'nit' => ['nullable', 'string', 'max:20'],
            'contactos' => ['required', 'array', 'min:1'],
            'contactos.*.id' => [
                'nullable',
                'integer',
                Rule::exists('com_cliente_contactos', 'id')
                    ->where('cliente_id', $clienteId)
                    ->whereNull('deleted_at'),
            ],
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
            'contactos.*.id.exists' => 'Uno de los contactos enviados no pertenece a este cliente.',
            'contactos.*.tipo.enum' => 'El tipo de contacto no es válido.',
        ];
    }
}
