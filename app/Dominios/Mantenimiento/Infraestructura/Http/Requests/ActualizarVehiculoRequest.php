<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Requests;

use App\Dominios\Mantenimiento\Dominio\EstadoVehiculo;
use App\Dominios\Mantenimiento\Dominio\TipoCombustibleVehiculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/vehiculos/{vehiculo}` (HU-40, tarea 50). Mismas reglas que
 * `CrearVehiculoRequest` — ver ese docblock, incluido `kilometraje_inicial`
 * (HU-84, tarea 99), editable también acá.
 */
final class ActualizarVehiculoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identificador' => ['required', 'string', 'max:40'],
            'marca' => ['nullable', 'string', 'max:60'],
            'modelo' => ['nullable', 'string', 'max:60'],
            'anio' => ['nullable', 'integer'],
            'combustible' => ['nullable', Rule::enum(TipoCombustibleVehiculo::class)],
            'es_4x4' => ['boolean'],
            'kilometraje_inicial' => ['nullable', 'numeric', 'min:0'],
            'kilometraje_actual' => ['nullable', 'numeric', 'min:0'],
            'base_id' => [
                'nullable',
                'integer',
                Rule::exists('per_bases', 'id')->whereNull('deleted_at'),
            ],
            'estado' => ['required', Rule::enum(EstadoVehiculo::class)],
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
