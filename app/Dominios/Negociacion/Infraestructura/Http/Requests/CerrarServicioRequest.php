<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/solicitudes/{id}/cierre`. */
final class CerrarServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'uuid_cliente' => ['required', 'uuid'], 'secuencia' => ['nullable', 'integer', 'min:0'], 'registrado_en' => ['nullable', 'date'],
            'hectareas_ejecutadas' => ['required', 'numeric', 'min:0', 'decimal:0,4'], 'nota' => ['nullable', 'string', 'max:500'],
        ];
    }
}
