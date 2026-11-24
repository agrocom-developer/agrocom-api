<?php

namespace App\Dominios\Seguridad\Contratos;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * El `username` o el `email` de una cuenta nueva ya existen. Forma parte del
 * contrato `RegistroCuentas` para que quien registra la capture sin conocer el
 * dominio de Seguridad. El campo sirve para devolver el error junto al input.
 */
final class CuentaDuplicada extends RuntimeException
{
    private function __construct(string $mensaje, public readonly string $campo)
    {
        parent::__construct($mensaje);
    }

    public static function porUsername(string $username): self
    {
        return new self(Texto::de('seguridad.errores.usuario_duplicado_username', ['username' => $username]), 'username');
    }

    public static function porEmail(string $email): self
    {
        return new self(Texto::de('seguridad.errores.usuario_duplicado_email', ['email' => $email]), 'email');
    }
}
