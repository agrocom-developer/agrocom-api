<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `DELETE /panel/equipos-trabajo/{equipoTrabajo}/recursos/{recurso}` (tarea
 * 72, HU-49): finaliza la vigencia del recurso asignado, no borra la fila —
 * mismo criterio que `FinalizarIntegranteEquipoRequest`.
 */
final class FinalizarRecursoEquipoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var EquipoRecurso $recurso */
        $recurso = $this->route('recurso');

        return [
            'hasta' => ['required', 'date', 'after_or_equal:'.$recurso->desde->toDateString()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'hasta.required' => __('personal.equipos_trabajo.error_vigencia_hasta_requerida'),
        ];
    }
}
