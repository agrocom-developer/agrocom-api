<?php

namespace App\Dominios\Reclamos\Infraestructura\Http\Requests;

use App\Dominios\Reclamos\Aplicacion\ParametrosDeReclamo;
use App\Dominios\Reclamos\Dominio\MotivoReclamo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cuerpo de `POST /api/solicitudes/{id}/reclamos`: el motivo del catálogo, el detalle
 * y, opcionalmente, el monto en reclamo (por defecto, todo el monto del servicio).
 * Que el motivo corresponda al lado de quien abre lo decide el caso de uso.
 */
final class AbrirReclamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', Rule::enum(MotivoReclamo::class)],
            'detalle' => ['required', 'string', 'min:3', 'max:'.app(ParametrosDeReclamo::class)->textoMax()],
            'monto_en_reclamo' => ['nullable', 'string', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('reclamos.campos');
    }
}
