<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Contratos\ReglasDeContacto;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Reglas de los datos de una cuenta nueva (nombre, login, correo y contraseña),
 * compartidas por los registros del marketplace. El login es un identificador,
 * nunca un correo (memoria del proyecto), y único entre cuentas vivas.
 */
final class ReglasDeCuenta
{
    /**
     * El correo (de respaldo) es único entre las cuentas del MISMO tipo; todas las cuentas que
     * crean estos registros son de proveedor, salvo que se pida otro tipo.
     *
     * @return array<string, list<mixed>>
     */
    public static function reglas(TipoCuenta $tipo = TipoCuenta::Proveedor): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'username' => [
                'required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('sec_user', 'username')->whereNull('deleted_at'),
            ],
            'email' => ['nullable', 'email:rfc', 'max:150', ReglasDeContacto::correoUnico($tipo)],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'max:100'],
        ];
    }
}
