<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use Illuminate\Validation\Rule;

/** Cuerpo de `POST /api/proveedores/documentos`: además del documento, a qué sujeto pertenece. */
final class CargarDocumentoDeSujetoRequest extends CargarDocumentoRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // El dron se carga por `/api/proveedores/drones/{dron}/documentos`: lo resuelve Flota.
            'sujeto_tipo' => ['required', Rule::in(['proveedor', 'piloto', 'auxiliar'])],
            'sujeto_id' => ['required', 'integer', 'min:1'],
            ...parent::rules(),
        ];
    }
}
