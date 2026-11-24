<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de `POST /api/solicitudes/{id}/ofertas` (`negociacion-contrato.md` §2, regla 1). */
final class OfertarRequest extends FormRequest
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
            'tipo' => ['required', Rule::in(['acepta_propuesta', 'contraoferta'])],
            'precio_ha' => ['required_if:tipo,contraoferta', 'nullable', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,4'],
            'equipo_id' => ['nullable', 'integer'],
            'nota' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('negociacion.campos');
    }
}
