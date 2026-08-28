<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\RolNoAsignado;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;

/**
 * Fija o limpia `sec_user_preferencia.rol_preferido_id` — el checkbox
 * "Entrar siempre con este rol" de la pantalla de selección (quinta vuelta,
 * maqueta 5c). Con la preferencia fijada, el login y el middleware activan
 * ese rol solos y el selector se saltea; reaparece únicamente al cambiar de
 * rol explícitamente desde el menú (ver `RolActivoController::create()`).
 *
 * NO activa ningún rol (eso es {@see ElegirRolActivo}, el único punto que
 * escribe el rol activo de sesión — ADR 0004) ni redefine el rol activo como
 * atributo de cuenta: es una preferencia de conveniencia que además se
 * revalida como "rol vivo" cada vez que se intenta usar. Autoservicio, igual
 * que {@see ActualizarPreferenciaUsuario}: el usuario administra su propia
 * preferencia, nunca la de otro.
 */
final class RecordarRolPreferido
{
    /**
     * @param  ?int  $idRol  El rol a recordar, o `null` para volver a
     *                       "preguntar en cada login".
     *
     * @throws RolNoAsignado si `$idRol` no es un rol vivo del usuario — la
     *                       preferencia nunca apunta a un rol que el usuario
     *                       no podría activar hoy.
     */
    public function ejecutar(SecUser $usuario, ?int $idRol): SecUserPreferencia
    {
        if ($idRol !== null && ! in_array($idRol, $usuario->idsDeRolesActivos(), true)) {
            throw RolNoAsignado::paraUsuario($usuario->id, $idRol);
        }

        $preferencia = SecUserPreferencia::query()->firstOrNew(['user_id' => $usuario->id]);

        $preferencia->rol_preferido_id = $idRol;

        if (! $preferencia->exists) {
            $preferencia->created_by = $usuario->id;
        }
        $preferencia->updated_by = $usuario->id;

        $preferencia->save();

        return $preferencia->refresh();
    }
}
