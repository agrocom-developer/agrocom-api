<?php

namespace App\Dominios\Disputas\Infraestructura\Http\Requests;

use App\Dominios\Disputas\Aplicacion\ParametrosDeDisputa;
use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/disputas/{id}/apelar`: las pruebas nuevas en que se apoya la apelación. */
final class ApelarDisputaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pruebas' => ['required', 'string', 'min:3', 'max:'.app(ParametrosDeDisputa::class)->textoMax()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('disputas.campos');
    }
}
