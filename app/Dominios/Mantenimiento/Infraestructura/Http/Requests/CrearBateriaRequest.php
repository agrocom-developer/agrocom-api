<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Requests;

use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/baterias` (HU-39, tarea 51). La autorización (permiso
 * `mantenimiento.bateria.crear`) se verifica en el controlador, contra el
 * rol activo — no acá, mismo criterio que el resto del panel.
 *
 * `ciclos_acumulados` es editable ya en el alta: una batería puede entrar
 * al catálogo con uso previo, no siempre arranca en 0.
 *
 * `base_id` es opcional (FK nullable) — una batería sin base asignada es un
 * caso válido. Cuando viene, tiene que apuntar a una base VIVA
 * (`whereNull('deleted_at')`), mismo criterio que `base_id` en
 * `CrearVehiculoRequest`.
 */
final class CrearBateriaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identificador' => ['required', 'string', 'max:40'],
            'ciclos_acumulados' => ['required', 'integer', 'min:0'],
            'base_id' => [
                'nullable',
                'integer',
                Rule::exists('per_bases', 'id')->whereNull('deleted_at'),
            ],
            'estado' => ['required', Rule::enum(EstadoBateria::class)],
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
