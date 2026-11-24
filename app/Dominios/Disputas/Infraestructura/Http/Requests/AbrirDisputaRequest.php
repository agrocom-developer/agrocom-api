<?php

namespace App\Dominios\Disputas\Infraestructura\Http\Requests;

use App\Dominios\Disputas\Aplicacion\ParametrosDeDisputa;
use App\Dominios\Disputas\Dominio\MotivoDisputa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cuerpo de `POST /api/solicitudes/{id}/disputas`: el motivo del catálogo, el detalle
 * y, opcionalmente, el monto en disputa (por defecto, todo el monto del servicio).
 * Que el motivo corresponda al lado de quien abre lo decide el caso de uso.
 */
final class AbrirDisputaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', Rule::enum(MotivoDisputa::class)],
            'detalle' => ['required', 'string', 'min:3', 'max:'.app(ParametrosDeDisputa::class)->textoMax()],
            'monto_en_disputa' => ['nullable', 'string', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('disputas.campos');
    }
}
