<?php

namespace App\Dominios\Reclamos\Infraestructura\Http\Requests;

use App\Dominios\Reclamos\Aplicacion\ParametrosDeReclamo;
use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/reclamos/{id}/mensajes` y del formulario de soporte del panel: el texto del mensaje. */
final class MensajeDeReclamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'texto' => ['required', 'string', 'min:1', 'max:'.app(ParametrosDeReclamo::class)->textoMax()],
            'pide_informacion' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('reclamos.campos');
    }
}
