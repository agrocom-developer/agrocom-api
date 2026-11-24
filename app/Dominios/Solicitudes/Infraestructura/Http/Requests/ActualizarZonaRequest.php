<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Requests;

use App\Dominios\Compartido\Dominio\Macrorregion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de `PUT /panel/zonas/{zona}`: el nombre y la macrorregión a la que pertenece. */
final class ActualizarZonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:80'],
            'macrorregion' => ['required', Rule::in(Macrorregion::valores())],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('solicitudes.campos');
    }
}
