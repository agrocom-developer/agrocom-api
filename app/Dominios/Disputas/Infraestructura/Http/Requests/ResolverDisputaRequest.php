<?php

namespace App\Dominios\Disputas\Infraestructura\Http\Requests;

use App\Dominios\Disputas\Aplicacion\ParametrosDeDisputa;
use App\Dominios\Disputas\Dominio\ResultadoDisputa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Formulario del panel para resolver una disputa: resultado, reembolso, motivo y marcas. */
final class ResolverDisputaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resultado' => ['required', 'string', Rule::enum(ResultadoDisputa::class)],
            'monto_reembolso' => ['nullable', 'string', 'regex:/^\d{1,12}([.,]\d{1,2})?$/'],
            'motivo' => ['required', 'string', 'min:3', 'max:'.app(ParametrosDeDisputa::class)->textoMax()],
            'derivada_a_seguro' => ['nullable', 'boolean'],
            'mala_fe_cliente' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('disputas.campos');
    }
}
