<?php

namespace App\Dominios\Seguridad\Contratos;

/**
 * Tipo de cuenta de `sec_user` (ADR 0046, punto 1): `panel` es el personal de
 * Agrocom (un solo rol del panel), `cliente` entra solo a la app
 * del cliente y `proveedor` solo a la app del piloto. Una cuenta tiene un solo
 * tipo para siempre: no hay conversión, ni selector de rol, ni un login que sirva
 * a las dos apps.
 *
 * Vive en `Contratos/` porque otros módulos lo piden al registrar una cuenta
 * ({@see RegistroCuentas::crear()}) sin importar `SecUser` (ADR 0003, regla 2).
 */
enum TipoCuenta: string
{
    case Panel = 'panel';
    case Cliente = 'cliente';
    case Proveedor = 'proveedor';

    /** Las cuentas de app: las que reciben token de dispositivo. */
    public function esDeApp(): bool
    {
        return $this !== self::Panel;
    }

    /**
     * La app desde la que se ingresa. El flavor del piloto se llama `piloto` y
     * su tipo de cuenta `proveedor` (ADR 0046): se acepta cualquiera de los dos
     * nombres y se normaliza.
     */
    public static function dePedidoDeApp(string $app): ?self
    {
        return match (mb_strtolower(trim($app))) {
            'cliente' => self::Cliente,
            'proveedor', 'piloto' => self::Proveedor,
            default => null,
        };
    }

    /** Nombre de la app que le corresponde, para el mensaje al usuario. */
    public function nombreDeApp(): string
    {
        return match ($this) {
            self::Cliente => 'Agrocom',
            self::Proveedor => 'Agrocom Piloto',
            self::Panel => 'el panel',
        };
    }
}
