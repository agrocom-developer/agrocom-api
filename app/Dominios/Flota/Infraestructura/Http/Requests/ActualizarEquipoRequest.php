<?php

namespace App\Dominios\Flota\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cuerpo de `PUT /api/proveedores/equipos/{id}`: solo se cambia el auxiliar o se
 * cierra la vigencia. El piloto y el dron de un equipo no se cambian: ese es
 * otro equipo.
 */
final class ActualizarEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'auxiliar_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'vigente_hasta' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('flota.campos');
    }
}
