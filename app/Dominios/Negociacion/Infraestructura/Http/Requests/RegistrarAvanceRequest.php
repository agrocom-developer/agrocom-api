<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/solicitudes/{id}/avance`. */
final class RegistrarAvanceRequest extends FormRequest
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
            'hectareas_ejecutadas' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'], 'porcentaje' => ['nullable', 'numeric', 'min:0', 'max:105', 'decimal:0,2'],
            'nota' => ['nullable', 'string', 'max:500'], 'motivo_correccion' => ['nullable', 'string', 'max:500'],
        ];
    }
}
