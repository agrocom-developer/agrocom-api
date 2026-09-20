<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use App\Dominios\Personal\Dominio\RecursoTipoEquipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/cuadrillas/{equipoTrabajo}/recursos` (tarea 72, HU-49).
 * Sin `Rule::exists` para `recurso_id`: la tabla de destino depende de
 * `recurso_tipo` (`ope_drones`/`man_vehiculos`/`man_generadores`, tres
 * tablas de dos módulos distintos) y esta clase no resuelve esa
 * indirección — la existencia y el estado activo del recurso se verifican
 * en `AsignarRecursoEquipo::existeYActivo()`, DENTRO del caso de uso, que es
 * donde el prompt de la tarea pide que se rechace (ver acceptance
 * criteria: "se rechaza en el caso de uso", no en la validación de forma).
 */
final class AsignarRecursoEquipoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'recurso_tipo' => ['required', Rule::enum(RecursoTipoEquipo::class)],
            'recurso_id' => ['required', 'integer', 'min:1'],
            'desde' => ['required', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'recurso_tipo.required' => __('personal.equipos_trabajo.error_recurso_tipo_requerido'),
            'recurso_id.required' => __('personal.equipos_trabajo.error_recurso_id_requerido'),
            'desde.required' => __('personal.equipos_trabajo.error_recurso_desde_requerida'),
        ];
    }
}
