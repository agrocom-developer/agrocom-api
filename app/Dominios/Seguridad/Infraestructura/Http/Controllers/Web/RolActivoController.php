<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\ElegirRolActivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ActualizarRolActivoRequest;
use Illuminate\Http\JsonResponse;

/**
 * Cambio de rol activo sin volver a loguearse (ADR 0004, extensión
 * 27/8/2026, punto 4). Adaptador delgado: valida forma, invoca
 * {@see ElegirRolActivo} (que revalida contra los roles vivos del usuario y
 * escribe la sesión), responde. Si el rol no está asignado o ya no está
 * vivo, `ElegirRolActivo` lanza `RolNoAsignado` (una `AuthorizationException`)
 * que el manejador de excepciones del framework traduce a 403 sin mapeo
 * adicional acá — mismo patrón que `PermisoDenegado` en `AsignarRolesUsuario`.
 *
 * No cruza ningún límite de autenticación (ADR 0004, extensión 27/8/2026,
 * punto 4): sigue siendo el mismo `sec_user.id` ya autenticado por la ruta
 * (`auth:interno`), por eso no regenera sesión ni token CSRF.
 */
final class RolActivoController
{
    public function update(ActualizarRolActivoRequest $request, ElegirRolActivo $elegirRolActivo): JsonResponse
    {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');

        $rol = $elegirRolActivo->ejecutar($usuario, (int) $request->validated('id_role'));

        return response()->json([
            'rol_activo_id' => $rol->id,
            'rol_activo_nombre' => $rol->name,
        ]);
    }
}
