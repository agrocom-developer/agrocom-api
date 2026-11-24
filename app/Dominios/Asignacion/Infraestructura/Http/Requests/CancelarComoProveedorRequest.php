<?php

namespace App\Dominios\Asignacion\Infraestructura\Http\Requests;

use App\Dominios\Asignacion\Dominio\CausaCancelacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de `POST /api/solicitudes/{id}/cancelar-proveedor`. */
final class CancelarComoProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:3', 'max:1000'],
            'causa' => ['nullable', 'string', Rule::enum(CausaCancelacion::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('asignacion.campos');
    }
}
