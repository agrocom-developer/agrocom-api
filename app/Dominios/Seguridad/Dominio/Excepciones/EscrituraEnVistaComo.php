<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Se intentó cambiar algo mientras la sesión mira el sistema como otra
 * persona (tarea 140). Esa vista es estrictamente de lectura: ni siquiera lo
 * que la persona observada tendría permiso de hacer está disponible.
 *
 * Extiende `AuthorizationException` para responder 403, igual que
 * {@see PermisoDenegado}: el rechazo es del servidor, no de un botón que la
 * interfaz decidió no mostrar.
 */
final class EscrituraEnVistaComo extends AuthorizationException
{
    public static function porSerSoloLectura(): self
    {
        return new self(Texto::de('seguridad.errores.vista_como_solo_lectura'));
    }
}
