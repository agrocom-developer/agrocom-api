<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/cuadrillas/{equipoTrabajo}/estado` (tarea "cuadrillas-estadias",
 * 19/9/2026). A diferencia de `CambiarEstadoCampaniaRequest`, acá los DOS
 * valores del enum son destinos válidos (`activo ⇄ inactivo`, ida y vuelta) —
 * no hay un estado de alta que quede fuera de los destinos posibles.
 */
final class CambiarEstadoEquipoTrabajoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoEquipoTrabajo::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'estado.required' => __('personal.equipos_trabajo.error_estado_requerido'),
        ];
    }
}
