<?php

namespace App\Dominios\Seguridad\Dominio\Excepciones;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * El rol que se intenta activar (al elegir en el login o al cambiar de rol
 * activo sin volver a loguearse) no está entre los roles vivos del usuario
 * — nunca tuvo ese rol, se lo revocaron, o el rol/la asignación están
 * desactivados a nivel de catálogo (ADR 0004, extensión 27/8/2026, puntos 2
 * y 4).
 *
 * Extiende `AuthorizationException` (mismo patrón que {@see PermisoDenegado})
 * para que el manejador de excepciones del framework la traduzca a 403 sin
 * mapeo adicional en el controlador — nunca se confía en que el `<select>`
 * del panel solo ofrecía roles legítimos: el servidor vuelve a verificar
 * aunque el valor venga de una UI propia.
 */
final class RolNoAsignado extends AuthorizationException
{
    public static function paraUsuario(int $idUsuario, int $idRolDeseado): self
    {
        return new self(
            "El usuario #{$idUsuario} no tiene asignado (o ya no tiene vivo) el rol #{$idRolDeseado}.",
        );
    }
}
