<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Reglas de los datos de una cuenta nueva (nombre, login, correo y contraseña),
 * compartidas por los registros del marketplace. El login es un identificador,
 * nunca un correo (memoria del proyecto), y único entre cuentas vivas.
 */
final class ReglasDeCuenta
{
    /** @return array<string, list<mixed>> */
    public static function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'username' => [
                'required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('sec_user', 'username')->whereNull('deleted_at'),
            ],
            'email' => ['nullable', 'email:rfc', 'max:150', Rule::unique('sec_user', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'max:100'],
        ];
    }
}
