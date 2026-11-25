<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Consulta de `GET /api/documentos`. */
final class ListarDocumentosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sujeto_tipo' => ['required', 'in:proveedor,piloto,auxiliar'],
            'sujeto_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
