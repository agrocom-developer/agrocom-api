<?php

namespace App\Dominios\Calificaciones\Infraestructura\Http\Requests;

use App\Dominios\Calificaciones\Aplicacion\ParametrosDeCalificacion;
use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/solicitudes/{id}/calificar-cliente`: la calificación privada del proveedor al cliente. */
final class CalificarClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'uuid_cliente' => ['required', 'uuid'],
            'estrellas' => ['required', 'integer', 'between:1,5'],
            'etiquetas' => ['nullable', 'array', 'max:7'],
            'etiquetas.*' => ['string', 'max:30'],
            'comentario' => ['nullable', 'string', 'max:'.app(ParametrosDeCalificacion::class)->comentarioMax()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('calificaciones.campos');
    }
}
