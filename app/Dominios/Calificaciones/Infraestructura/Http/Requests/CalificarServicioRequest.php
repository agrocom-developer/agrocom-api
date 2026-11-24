<?php

namespace App\Dominios\Calificaciones\Infraestructura\Http\Requests;

use App\Dominios\Calificaciones\Aplicacion\ParametrosDeCalificacion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cuerpo de `POST /api/solicitudes/{id}/calificaciones`: de una a tres
 * calificaciones (proveedor, piloto y auxiliar), cada una con el `uuid_cliente` que
 * nace en el dispositivo y hace idempotente el reintento.
 */
final class CalificarServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'calificaciones' => ['required', 'array', 'min:1', 'max:3'],
            'calificaciones.*.uuid_cliente' => ['required', 'uuid'],
            'calificaciones.*.sujeto_tipo' => ['required', 'string', 'in:proveedor,piloto,auxiliar'],
            'calificaciones.*.estrellas' => ['required', 'integer', 'between:1,5'],
            'calificaciones.*.etiquetas' => ['nullable', 'array', 'max:7'],
            'calificaciones.*.etiquetas.*' => ['string', 'max:30'],
            'calificaciones.*.comentario' => ['nullable', 'string', 'max:'.app(ParametrosDeCalificacion::class)->comentarioMax()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('calificaciones.campos');
    }
}
