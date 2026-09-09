<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use App\Dominios\Comercial\Dominio\EstadoContrato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /panel/contratos/{contrato}/estado` (HU-23, tarea 34). `borrador`
 * queda fuera de los destinos válidos a propósito: ningún estado lo admite
 * en `TransicionesContrato` (nunca es destino de una transición, solo el
 * estado inicial de alta), así que no tiene sentido ofrecerlo como opción.
 */
final class CambiarEstadoContratoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estado' => [
                'required',
                Rule::in([
                    EstadoContrato::Vigente->value,
                    EstadoContrato::Finalizado->value,
                    EstadoContrato::Cancelado->value,
                ]),
            ],
        ];
    }
}
