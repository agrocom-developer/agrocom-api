<?php

namespace App\Dominios\Reclamos\Infraestructura\Http\Requests;

use App\Dominios\Reclamos\Aplicacion\ParametrosDeReclamo;
use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/reclamos/{id}/apelar`: las pruebas nuevas en que se apoya la apelación. */
final class ApelarReclamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pruebas' => ['required', 'string', 'min:3', 'max:'.app(ParametrosDeReclamo::class)->textoMax()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('reclamos.campos');
    }
}
