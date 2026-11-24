<?php

namespace App\Dominios\Calificaciones\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Formulario del panel para dejar visible una calificación reportada u oculta: el motivo es obligatorio. */
final class RestablecerCalificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('calificaciones.campos');
    }
}
