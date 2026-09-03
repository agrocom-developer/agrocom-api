<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Baja lógica de una cuenta de usuario (HU-45, tarea 39). Soft delete, nunca
 * físico (ADR 0007) — la fila queda para auditoría con quién la dio de baja.
 *
 * A diferencia de `EliminarPersona`/`EliminarOrden` (que confían la
 * autorización al controlador), acá el permiso se verifica DENTRO, con el
 * mismo criterio que {@see AsignarRolesUsuario}: el caso de uso recibe el
 * `$actor` explícito y no asume contexto HTTP — Seguridad es de los módulos
 * que CLAUDE.md marca como "no delegar sin revisión línea por línea".
 */
final class EliminarUsuario
{
    private const PERMISO_ELIMINAR = 'seguridad.usuario.eliminar';

    /** @throws PermisoDenegado si al actor le falta el permiso de baja. */
    public function ejecutar(SecUser $actor, SecUser $usuario): void
    {
        if (! $actor->tienePermiso(self::PERMISO_ELIMINAR)) {
            throw PermisoDenegado::porFaltaDePermiso(self::PERMISO_ELIMINAR);
        }

        $usuario->updated_by = $actor->id;
        $usuario->save();

        $usuario->delete();
    }
}
