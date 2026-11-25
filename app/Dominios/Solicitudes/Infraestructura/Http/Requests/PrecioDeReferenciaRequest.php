<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Requests;

use App\Dominios\Solicitudes\Dominio\TipoAplicacionServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Consulta de `GET /api/precios-referencia`. */
final class PrecioDeReferenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo_aplicacion' => ['required', Rule::enum(TipoAplicacionServicio::class)],
            'zona_id' => ['nullable', 'integer', Rule::exists(Zona::class, 'id')->whereNull('deleted_at')],
            'macrorregion' => ['nullable', 'string', 'max:30', Rule::exists(Zona::class, 'macrorregion')->whereNull('deleted_at')],
        ];
    }
}
