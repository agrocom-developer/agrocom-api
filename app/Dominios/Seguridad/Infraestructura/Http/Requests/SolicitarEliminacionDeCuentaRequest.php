<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** `POST /api/cuenta/eliminacion` (ADR 0047, punto 2): basta la contraseña o el código; sin ninguno no se acepta nada. */
final class SolicitarEliminacionDeCuentaRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string', 'required_without:codigo'],
            'codigo' => ['nullable', 'string', 'max:10', 'required_without:password'],
        ];
    }
}
