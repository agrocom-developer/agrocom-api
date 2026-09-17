<?php

namespace App\Dominios\Campania\Infraestructura\Http\Requests;

use App\Dominios\Campania\Dominio\EstadoCampania;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/campanias/{campania}/estado` (ADR 0015 punto 1, tarea 69).
 * `planificada` queda fuera de los destinos válidos a propósito: ningún
 * estado lo admite en `TransicionesCampania` (nunca es destino de una
 * transición, solo el estado inicial de alta), mismo criterio que
 * `CambiarEstadoContratoRequest` con `borrador`.
 */
final class CambiarEstadoCampaniaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estado' => [
                'required',
                Rule::in([
                    EstadoCampania::Abierta->value,
                    EstadoCampania::Cerrada->value,
                ]),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'estado.required' => __('campania.campanias.error_estado_requerido'),
        ];
    }
}
