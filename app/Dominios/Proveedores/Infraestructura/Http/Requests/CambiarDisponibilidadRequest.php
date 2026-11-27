<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use App\Dominios\Proveedores\Dominio\Disponibilidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de `PUT /api/proveedores/mi/disponibilidad` y de `PUT /api/proveedores/pilotos/{id}/disponibilidad`. */
final class CambiarDisponibilidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'disponibilidad' => ['required', 'string', Rule::in(Disponibilidad::valores())],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('proveedores.campos');
    }
}
