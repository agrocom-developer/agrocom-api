<?php

namespace App\Dominios\Asignacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Formulario del panel para levantar una sanción con motivo. */
final class LevantarSancionRequest extends FormRequest
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
        return __('asignacion.campos');
    }
}
