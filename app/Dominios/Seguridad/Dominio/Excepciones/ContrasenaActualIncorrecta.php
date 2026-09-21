<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * El cambio de contraseña propio (tarea 66, `/panel/perfil` y
 * `/portal/perfil`) exige la contraseña ACTUAL para fijar una nueva —
 * verificada con `Hash::check`, nunca confiando en que el pedido venga de
 * quien dice ser solo porque trae una sesión válida.
 */
final class ContrasenaActualIncorrecta extends RuntimeException
{
    public static function porIntento(): self
    {
        return new self(Texto::de('seguridad.errores.contrasena_actual_incorrecta'));
    }
}
