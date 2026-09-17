<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Traducción legible de la violación del índice único de `sec_role.name`.
 * Mismo criterio que {@see UsuarioDuplicado}: el caso de uso que persiste el
 * rol captura la `QueryException` y la relanza así — nunca deja propagarse el
 * 500 crudo del motor.
 *
 * `name` es UNIQUE PLANO en `sec_role`, no parcial (ver la migración): un rol
 * dado de baja NO libera su nombre, porque es catálogo de sistema y no un
 * registro operativo. El mensaje lo dice, porque si no el choque contra un
 * rol soft-deleted es indistinguible de un typo.
 */
final class RolDuplicado extends RuntimeException
{
    public static function porNombre(string $nombre): self
    {
        return new self(Texto::de('seguridad.errores.rol_duplicado', ['nombre' => $nombre]));
    }
}
