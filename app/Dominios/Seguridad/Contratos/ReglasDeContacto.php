<?php

namespace App\Dominios\Seguridad\Contratos;

use App\Dominios\Seguridad\Dominio\NumeroDeTelefono;
use App\Dominios\Seguridad\Dominio\TipoDocumento;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Lo que los registros del marketplace piden a Seguridad sobre teléfono, correo y documento
 * (ADR 0046, puntos 7, 9 y 24): el formato (E.164, Bolivia por defecto), el correo único POR
 * TIPO de cuenta y los tipos de documento. Vive en `Contratos/` para que `Proveedores` y
 * `Solicitudes` validen y normalicen sin importar `SecUser` ni el dominio de Seguridad.
 */
final class ReglasDeContacto
{
    /** El teléfono, en E.164, o `null` si no es un teléfono reconocible ni se envió. */
    public static function telefono(?string $crudo): ?string
    {
        return $crudo === null || trim($crudo) === '' ? null : NumeroDeTelefono::normalizar($crudo);
    }

    /** Regla de validación de un teléfono: lo que `NumeroDeTelefono` no entiende se rechaza. */
    public static function reglaTelefono(): Closure
    {
        return static function (string $atributo, mixed $valor, Closure $falla): void {
            if (! is_string($valor) || NumeroDeTelefono::normalizar($valor) === null) {
                $falla(__('seguridad.validacion.telefono_invalido'));
            }
        };
    }

    /** El correo es único entre las cuentas vivas del MISMO tipo (el mismo correo puede estar en una de cliente y una de proveedor). */
    public static function correoUnico(TipoCuenta $tipo): Unique
    {
        return Rule::unique('sec_user', 'email')->where('tipo_cuenta', $tipo->value)->whereNull('deleted_at');
    }

    /** @return list<string> `ci`, `pasaporte`, `licencia_conducir`. */
    public static function tiposDeDocumento(): array
    {
        return array_map(static fn (TipoDocumento $tipo): string => $tipo->value, TipoDocumento::cases());
    }
}
