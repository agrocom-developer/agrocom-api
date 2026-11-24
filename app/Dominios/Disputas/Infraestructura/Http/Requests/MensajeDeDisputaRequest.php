<?php

namespace App\Dominios\Disputas\Infraestructura\Http\Requests;

use App\Dominios\Disputas\Aplicacion\ParametrosDeDisputa;
use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/disputas/{id}/mensajes` y del formulario de soporte del panel: el texto del mensaje. */
final class MensajeDeDisputaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'texto' => ['required', 'string', 'min:1', 'max:'.app(ParametrosDeDisputa::class)->textoMax()],
            'pide_informacion' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('disputas.campos');
    }
}
