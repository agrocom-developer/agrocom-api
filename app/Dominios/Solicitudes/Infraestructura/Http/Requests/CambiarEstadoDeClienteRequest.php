<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /panel/clientes/{cliente}/estado`: suspender exige motivo; reactivar no. */
final class CambiarEstadoDeClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'accion' => ['required', 'in:suspender,reactivar'],
            'motivo' => ['required_if:accion,suspender', 'nullable', 'string', 'min:3', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('solicitudes.campos');
    }
}
