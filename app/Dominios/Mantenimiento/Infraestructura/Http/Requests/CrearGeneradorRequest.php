<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Requests;

use App\Dominios\Mantenimiento\Dominio\EstadoGenerador;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/generadores` (tarea 72, HU-49). La autorización (permiso
 * `mantenimiento.generador.crear`) se verifica en el controlador, contra el
 * rol activo — no acá, mismo criterio que el resto del panel.
 *
 * `base_id` es opcional (FK nullable) — un generador sin base asignada es un
 * caso válido. Cuando viene, tiene que apuntar a una base VIVA
 * (`whereNull('deleted_at')`), mismo criterio que `base_id` en
 * `CrearVehiculoRequest`.
 */
final class CrearGeneradorRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identificador' => ['required', 'string', 'max:40'],
            'modelo' => ['nullable', 'string', 'max:60'],
            'base_id' => [
                'nullable',
                'integer',
                Rule::exists('per_bases', 'id')->whereNull('deleted_at'),
            ],
            'estado' => ['required', Rule::enum(EstadoGenerador::class)],
            'horas_uso' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'base_id.exists' => 'La base seleccionada no es válida.',
        ];
    }
}
