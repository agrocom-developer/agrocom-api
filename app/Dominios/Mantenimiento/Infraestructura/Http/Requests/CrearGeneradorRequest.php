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
 *
 * `horas_inicial`/`horas_actual` (HU-86, tarea 101) reemplazan a
 * `horas_uso`, ambas opcionales. `gte:horas_inicial` rechaza como error de
 * validación (nunca como excepción de base de datos) que el generador entre
 * con menos horas de las que ya acumuló — solo se evalúa cuando las dos
 * vienen cargadas, mismo criterio que el `CHECK` de la migración.
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
            'horas_inicial' => ['nullable', 'numeric', 'min:0'],
            'horas_actual' => ['nullable', 'numeric', 'min:0', 'gte:horas_inicial'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'identificador.required' => __('mantenimiento.validacion.generador_identificador_requerido'),
            'estado.required' => __('mantenimiento.validacion.generador_estado_requerido'),
            'base_id.exists' => __('mantenimiento.validacion.base_invalida'),
            'horas_actual.gte' => __('mantenimiento.validacion.horas_actual_menor'),
        ];
    }
}
