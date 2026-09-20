<?php

namespace App\Dominios\Seguridad\Infraestructura;

use App\Dominios\Seguridad\Contratos\DatosUsuarioDePersona;
use App\Dominios\Seguridad\Contratos\LecturaUsuarioDePersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Implementación Eloquent de {@see LecturaUsuarioDePersona}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `AutorizacionPanelWebSesion`: esa subcarpeta es solo para modelos.
 *
 * El soft delete de `ModeloDominio` deja afuera la cuenta dada de baja y sus
 * asignaciones de rol borradas. `activo` es `sec_user.state` (verdadero =
 * puede iniciar sesión; falso = bloqueada, ver `AlternarBloqueoUsuario`).
 */
final class LecturaUsuarioDePersonaEloquent implements LecturaUsuarioDePersona
{
    public function dePersona(int $personaId): ?DatosUsuarioDePersona
    {
        $usuario = SecUser::query()->where('persona_id', $personaId)->orderBy('id')->first();

        if ($usuario === null) {
            return null;
        }

        return new DatosUsuarioDePersona(
            id: $usuario->id,
            username: $usuario->username,
            activo: $usuario->state,
            roles: $usuario->asignacionesDeRol()->count(),
        );
    }
}
