<?php

namespace App\Dominios\Proveedores\Dominio;

/**
 * Interruptor manual de una cuenta de proveedor (ADR 0045, punto 8): sin calendario y
 * sin que el sistema lo adivine. `ocupado` («no puedo tomar trabajo ahora») y `en_trabajo`
 * («estoy fumigando») tienen el mismo efecto —no se le avisa de nuevas solicitudes— y se
 * distinguen solo por el texto. No oculta el mapa ni impide ofertar.
 */
enum Disponibilidad: string
{
    case Disponible = 'disponible';
    case Ocupado = 'ocupado';
    case EnTrabajo = 'en_trabajo';

    /** ¿A una cuenta en este estado se le avisa de nuevas solicitudes? */
    public function recibeAvisos(): bool
    {
        return $this === self::Disponible;
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $caso): string => $caso->value, self::cases());
    }
}
