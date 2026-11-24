<?php

namespace App\Dominios\Flota\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `PUT /api/disponibilidad`. */
final class DeclararDisponibilidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'en_linea' => ['required', 'boolean'],
            'equipo_id' => ['nullable', 'integer', 'min:1'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('flota.campos');
    }
}
