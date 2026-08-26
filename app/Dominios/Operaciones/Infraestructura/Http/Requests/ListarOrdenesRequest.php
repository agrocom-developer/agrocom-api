<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del listado de órdenes (query string). `page`/`per_page` siguen la
 * convención de paginación de Laravel — son plumbing de infraestructura, no
 * vocabulario de dominio.
 */
class ListarOrdenesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // TODO(HU-03): la autorización real llega con Sanctum por dispositivo;
        // mientras tanto la ruta es pública y esto solo habilita la validación.
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'estado' => ['sometimes', Rule::enum(EstadoOrdenAplicacion::class)],
            'lote_id' => ['sometimes', 'integer', 'min:1'],
            'contrato_id' => ['sometimes', 'integer', 'min:1'],
            'nro_aplicacion' => ['sometimes', 'integer', 'min:1'],
            'vigentes' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
