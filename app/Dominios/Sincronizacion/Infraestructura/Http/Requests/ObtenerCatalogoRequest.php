<?php

namespace App\Dominios\Sincronizacion\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `desde` es el cursor opaco de continuación (ver `CursorCatalogo`): un
 * string cualquiera, nunca validado en su contenido — un valor corrupto se
 * trata como "primera sincronización", no como un 422 (ver el caso de uso).
 */
class ObtenerCatalogoRequest extends FormRequest
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
            'desde' => ['sometimes', 'string'],
        ];
    }
}
