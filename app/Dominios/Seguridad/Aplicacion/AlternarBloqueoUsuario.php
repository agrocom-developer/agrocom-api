<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Invierte `sec_user.state` (HU-45, tarea 39): el permiso
 * `seguridad.usuario.bloquear` ya estaba sembrado (`SeguridadSeeder`) sin
 * ninguna acción que lo consumiera. Bloquear NO es una baja (invariante 8 —
 * sigue vivo, sin `deleted_at`): solo le cierra el paso al login, que ya
 * filtra por `state` (`IniciarSesionPanel::ejecutar()`, `->where('state',
 * true)`) — este caso de uso no toca nada de eso, solo el flag.
 *
 * `save()` con `updated_by` seteado alcanza para la bitácora de auditoría
 * (invariante 9, trait `RegistraBitacora` de `SecUser`) — nada manual acá.
 *
 * Mismo criterio que {@see EliminarUsuario}: permiso verificado DENTRO,
 * contra el actor explícito, sin asumir contexto HTTP.
 */
final class AlternarBloqueoUsuario
{
    private const PERMISO_BLOQUEAR = 'seguridad.usuario.bloquear';

    /** @throws PermisoDenegado si al actor le falta el permiso de bloqueo. */
    public function ejecutar(SecUser $actor, SecUser $usuario): SecUser
    {
        if (! $actor->tienePermiso(self::PERMISO_BLOQUEAR)) {
            throw PermisoDenegado::porFaltaDePermiso(self::PERMISO_BLOQUEAR);
        }

        $usuario->state = ! $usuario->state;
        $usuario->updated_by = $actor->id;
        $usuario->save();

        return $usuario->refresh();
    }
}
