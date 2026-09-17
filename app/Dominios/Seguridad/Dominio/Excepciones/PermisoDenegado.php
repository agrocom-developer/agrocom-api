<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * El actor que ejecuta un caso de uso de Seguridad no tiene el permiso
 * abstracto requerido (HU-01, diseño `modulos-roles` §3): por ejemplo, un
 * encargado de operaciones sin `seguridad.usuario.asignar_rol_dueno`
 * intentando crear o editar un usuario con el rol `dueno`.
 *
 * Extiende la excepción de autorización propia del framework (no un
 * `abort()` de controlador) para que, cuando exista el endpoint HTTP, el
 * manejador de excepciones la traduzca a 403 sin ningún mapeo adicional —
 * hoy no hay controlador (HU-01 es puro backend) y los tests la capturan
 * directo sobre el caso de uso.
 */
final class PermisoDenegado extends AuthorizationException
{
    public static function porFaltaDePermiso(string $codigoPermiso): self
    {
        return new self(Texto::de('seguridad.errores.permiso_denegado', ['permiso' => $codigoPermiso]));
    }
}
