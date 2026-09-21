<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use App\Dominios\Personal\Dominio\RolEquipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/cuadrillas/{equipoTrabajo}/integrantes` (tarea 72,
 * HU-49). La autorización (permiso `personal.equipo_trabajo.editar`) se
 * verifica en el controlador. El solapamiento con OTROS equipos NO se valida
 * acá (no es un error de formulario, es un aviso de negocio que igual se
 * guarda) — lo evalúa `AsignarIntegranteEquipo` vía
 * `ValidadorSolapamientoVigencias`; esta clase solo valida forma.
 */
final class AsignarIntegranteEquipoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'persona_id' => [
                'required',
                'integer',
                Rule::exists('per_personas', 'id')->whereNull('deleted_at'),
            ],
            'rol_equipo' => ['required', Rule::enum(RolEquipo::class)],
            'desde' => ['required', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'persona_id.required' => __('personal.equipos_trabajo.error_integrante_persona_requerida'),
            'persona_id.exists' => __('personal.validacion.persona_invalida'),
            'rol_equipo.required' => __('personal.equipos_trabajo.error_integrante_rol_requerido'),
            'desde.required' => __('personal.equipos_trabajo.error_integrante_desde_requerida'),
        ];
    }
}
