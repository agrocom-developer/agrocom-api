<?php

namespace App\Dominios\Proveedores\Dominio;

/**
 * Tipo de proveedor (ADR 0028): `propio` es Agrocom, único y con prioridad de
 * asignación (ADR 0030); `independiente` es un piloto que es su propio
 * proveedor; `tercero` es una empresa o dueño de drones con pilotos a su cargo.
 */
enum TipoProveedor: string
{
    case Propio = 'propio';
    case Independiente = 'independiente';
    case Tercero = 'tercero';

    /** Tipos que una persona puede pedir al registrarse por su cuenta. */
    public function seRegistraPorSiMismo(): bool
    {
        return $this !== self::Propio;
    }

    /** Quien se registra como independiente es también el piloto de su proveedor. */
    public function titularEsPiloto(): bool
    {
        return $this === self::Independiente;
    }

    /** @return list<self> */
    public static function registrables(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $tipo): bool => $tipo->seRegistraPorSiMismo()));
    }
}
