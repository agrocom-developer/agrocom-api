<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Requests;

use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /panel/baterias/{bateria}` (HU-39, tarea 51). Mismas reglas que
 * `CrearBateriaRequest` — ver ese docblock.
 *
 * A propósito NO valida `ciclos_inicial` (HU-83, tarea 98): es inmutable
 * después del alta, así que aunque el formulario lo muestre de solo
 * lectura, si algo lo mandara igual no llegaría a `$datos` en el
 * controlador — `ActualizarBateria` no lo recibe.
 *
 * `motivo_correccion` (HU-87, tarea 102) es `nullable`: la validación de
 * "obligatorio SOLO si baja el contador" no puede vivir acá (esta clase no
 * conoce el valor actual de la batería) — la guarda real está en
 * `ActualizarBateria::ejecutar()`, ver su docblock.
 */
final class ActualizarBateriaRequest extends FormRequest
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
            'motivo_correccion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'identificador.required' => __('mantenimiento.validacion.bateria_identificador_requerido'),
            'ciclos_acumulados.required' => __('mantenimiento.validacion.ciclos_acumulados_requerido'),
            'estado.required' => __('mantenimiento.validacion.bateria_estado_requerido'),
            'base_id.exists' => __('mantenimiento.validacion.base_invalida'),
        ];
    }
}
