<?php

namespace App\Dominios\Seguridad\Contratos;

/**
 * Por dónde le llega un código a la persona (ADR 0046, punto 27): `whatsapp` y `sms` van al teléfono de la
 * cuenta y `correo` al correo de respaldo. La persona elige el canal.
 */
enum CanalDeCodigo: string
{
    case Whatsapp = 'whatsapp';
    case Sms = 'sms';
    case Correo = 'correo';

    /** Si el código va al teléfono de la cuenta. */
    public function esDeTelefono(): bool
    {
        return $this !== self::Correo;
    }
}
