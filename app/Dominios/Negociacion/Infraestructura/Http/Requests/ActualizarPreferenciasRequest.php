<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Requests;

use App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor\FiltrosDeProveedor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cuerpo de `PUT /api/notificaciones/preferencias`. Los filtros del proveedor
 * (`nuevas_solicitudes.<clave>`) son un mecanismo extensible: los valida cada filtro
 * registrado ({@see FiltrosDeProveedor}),
 * no esta lista de reglas.
 */
final class ActualizarPreferenciasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nuevas_solicitudes' => ['sometimes', 'array'],
            'nuevas_solicitudes.activo' => ['sometimes', 'boolean'],
            'ofertas' => ['sometimes', 'boolean'],
            'mensajes' => ['sometimes', 'boolean'],
            'avance' => ['sometimes', 'boolean'],
            'calificaciones' => ['sometimes', 'boolean'],
        ];
    }
}
