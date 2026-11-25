<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/clientes/mi/contactos` y de `PUT /api/clientes/mi/contactos/{id}`. */
final class GuardarContactoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'telefono' => ['required', 'string', 'max:30', 'regex:/^[0-9 +().-]{6,30}$/'],
            'cargo' => ['nullable', 'string', 'max:80'],
            'es_principal' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('solicitudes.campos');
    }
}
