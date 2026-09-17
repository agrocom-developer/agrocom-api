<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Traducción legible de la violación de un índice único parcial de
 * `sec_user` (HU-01, diseño `modulos-roles` §4): "un usuario nunca tiene dos
 * logins". El caso de uso que persiste `SecUser` captura la
 * `QueryException` de Postgres (código `23505`, unique_violation) y la
 * relanza como esta excepción — nunca deja propagarse el 500 crudo del
 * motor de base de datos.
 */
final class UsuarioDuplicado extends RuntimeException
{
    public static function porUsername(string $username): self
    {
        return new self(Texto::de('seguridad.errores.usuario_duplicado_username', ['username' => $username]));
    }

    public static function porPersona(int $personaId): self
    {
        return new self(Texto::de('seguridad.errores.usuario_duplicado_persona', ['persona_id' => $personaId]));
    }

    public static function porEmail(string $email): self
    {
        return new self(Texto::de('seguridad.errores.usuario_duplicado_email', ['email' => $email]));
    }
}
