<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/planes-mantenimiento` (HU-38, tarea 54). La autorización
 * (permiso `mantenimiento.plan.crear`) se verifica en el controlador, contra
 * el rol activo — no acá, mismo criterio que el resto del panel.
 *
 * `modelo` es texto libre (sin `exists` contra `ope_drones`): la correlación
 * es por igualdad de texto, no por catálogo cerrado — un plan puede darse de
 * alta para un modelo que todavía no tiene ningún dron cargado.
 */
final class CrearPlanMantenimientoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'modelo' => ['required', 'string', 'max:40'],
            'tarea' => ['required', 'string'],
            'horas_umbral' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'modelo.required' => __('mantenimiento.validacion.modelo_requerido'),
            'tarea.required' => __('mantenimiento.validacion.tarea_requerida'),
            'horas_umbral.required' => __('mantenimiento.validacion.horas_umbral_requerido'),
        ];
    }
}
