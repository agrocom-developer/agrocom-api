<?php

namespace App\Dominios\Asignacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Formulario del panel para asignar una solicitud a mano. */
final class ForzarAsignacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'proveedor_id' => ['required', 'integer', 'min:1'],
            'equipo_id' => ['nullable', 'integer', 'min:1'],
            'motivo' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('asignacion.campos');
    }
}
