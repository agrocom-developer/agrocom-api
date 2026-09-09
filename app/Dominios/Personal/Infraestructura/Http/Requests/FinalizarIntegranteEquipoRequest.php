<?php

namespace App\Dominios\Personal\Infraestructura\Http\Requests;

use App\Dominios\Personal\Infraestructura\Eloquent\EquipoIntegrante;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `DELETE /panel/equipos-trabajo/{equipoTrabajo}/integrantes/{integrante}`
 * (tarea 72, HU-49): finaliza la vigencia del integrante, no borra la fila
 * (ver `DesasignarIntegranteEquipo`). `hasta` tiene que ser posterior o
 * igual a la fecha `desde` YA guardada de esa fila — `after_or_equal` con un
 * valor dinámico porque no es otro campo del formulario, es un dato del
 * modelo de la ruta.
 */
final class FinalizarIntegranteEquipoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var EquipoIntegrante $integrante */
        $integrante = $this->route('integrante');

        return [
            'hasta' => ['required', 'date', 'after_or_equal:'.$integrante->desde->toDateString()],
        ];
    }
}
