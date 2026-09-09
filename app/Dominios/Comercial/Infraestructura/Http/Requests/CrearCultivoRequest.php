<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/cultivos` (HU-48, tarea 71). La autorización (permiso
 * `comercial.cultivo.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que el resto del panel.
 */
final class CrearCultivoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80'],
            'activo' => ['boolean'],
        ];
    }
}
