<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use App\Dominios\Negociacion\Aplicacion\ConfirmarPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de `POST /api/solicitudes/{id}/pago`. */
final class ConfirmarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'uuid_cliente' => ['required', 'uuid'],
            'metodo' => ['nullable', Rule::in(ConfirmarPago::METODOS)],
            'nota' => ['nullable', 'string', 'max:280'],
        ];
    }
}
