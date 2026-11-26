<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /api/auth/token` — login de la app de campo (HU-03).
 *
 * Login por `username`, nunca por correo (memoria del proyecto), declarando la
 * app de origen (`app`, ADR 0046). El
 * `uuid_dispositivo` lo genera el propio dispositivo, igual que el
 * `uuid_cliente` de los registros que nacen en campo (invariante 1): es lo
 * que permite que reintentar el login desde el mismo teléfono no acumule
 * tokens vivos.
 */
final class EmitirTokenDispositivoRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string'],
            'uuid_dispositivo' => ['required', 'uuid'],
            'nombre_dispositivo' => ['nullable', 'string', 'max:80'],
            // Con qué rol opera el dispositivo. Opcional: si el usuario tiene
            // un único rol vivo se resuelve solo, igual que en el login del
            // panel (ADR 0004, extensión 27/8/2026, punto 3).
            'role_id' => ['nullable', 'integer', 'min:1'],
            // La app desde la que se ingresa (ADR 0046): `cliente` o `proveedor` (el
            // flavor del piloto también puede mandar `piloto`, que se normaliza a
            // `proveedor`). TRANSITORIO hasta que las apps la envíen (fase F1 de field):
            // si falta, el tipo se deduce de la propia cuenta y no falla.
            'app' => ['nullable', 'string', Rule::in(['cliente', 'proveedor', 'piloto'])],
        ];
    }
}
