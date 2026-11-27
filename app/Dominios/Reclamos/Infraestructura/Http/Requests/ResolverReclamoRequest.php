<?php

namespace App\Dominios\Reclamos\Infraestructura\Http\Requests;

use App\Dominios\Reclamos\Aplicacion\ParametrosDeReclamo;
use App\Dominios\Reclamos\Dominio\ResultadoReclamo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Formulario del panel para resolver un reclamo: resultado, reembolso, motivo y marcas. */
final class ResolverReclamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resultado' => ['required', 'string', Rule::enum(ResultadoReclamo::class)],
            'monto_reembolso' => ['nullable', 'string', 'regex:/^\d{1,12}([.,]\d{1,2})?$/'],
            'motivo' => ['required', 'string', 'min:3', 'max:'.app(ParametrosDeReclamo::class)->textoMax()],
            'derivada_a_seguro' => ['nullable', 'boolean'],
            'mala_fe_cliente' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('reclamos.campos');
    }
}
