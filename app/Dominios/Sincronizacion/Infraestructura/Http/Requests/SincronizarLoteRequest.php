<?php

namespace App\Dominios\Sincronizacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida solo el sobre del lote (`registros` presente y arreglo): la forma
 * de cada registro según su `tipo` la valida `SincronizarLote` a través de
 * `AperturaTrabajo`/`AperturaSesion::intentarDesdeArreglo()`, que rechaza el
 * registro puntual en vez de lanzar. Validar los campos por tipo acá
 * devolvería un 422 para el lote completo ante un solo registro mal
 * formado — exactamente lo que la espec prohíbe ("un rechazo no frena el
 * resto del lote", §2.1 punto 3).
 */
class SincronizarLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización real es el guard `auth:sanctum` de la ruta
        // (token por dispositivo, HU-03); acá solo se valida la forma.
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'registros' => ['present', 'array'],
        ];
    }
}
