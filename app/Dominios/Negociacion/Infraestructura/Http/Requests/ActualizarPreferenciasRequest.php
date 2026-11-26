<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `PUT /api/preferencias` del proveedor. */
final class ActualizarPreferenciasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nuevas_solicitudes' => ['sometimes', 'array'],
            'nuevas_solicitudes.activo' => ['sometimes', 'boolean'],
            'nuevas_solicitudes.hectareas_minimas' => ['sometimes', 'nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'ofertas' => ['sometimes', 'boolean'],
            'mensajes' => ['sometimes', 'boolean'],
            'avance' => ['sometimes', 'boolean'],
            'calificaciones' => ['sometimes', 'boolean'],
        ];
    }
}
