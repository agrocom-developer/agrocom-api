<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\RolNoAsignado;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use Illuminate\Support\Facades\Session;

/**
 * Único punto que escribe `session('sec_rol_activo_id')` (ADR 0004,
 * extensión 27/8/2026, puntos 2 y 4): fija cuál de los roles vivos de
 * `sec_user_role` gobierna los permisos efectivos de la sesión actual del
 * panel. Nunca una columna en `sec_user` — el rol activo es estado de
 * sesión, no un atributo de la cuenta (misma extensión, alternativas
 * descartadas).
 *
 * Tres llamadores comparten esta única implementación, ninguno reescribe la
 * validación por su cuenta:
 * - El login, cuando el usuario tiene un único rol vivo (se activa solo).
 * - El endpoint de cambio de rol activo sin volver a loguearse (el usuario
 *   elige explícitamente, entre varios).
 * - El middleware `ResolverRolActivo`, para el mismo caso de "un único rol
 *   vivo" cuando la sesión llega sin rol activo resuelto.
 *
 * Revalida siempre contra la base (nunca confía en que `$idRolDeseado` venga
 * de una UI propia que ya filtraba las opciones): un request se puede
 * fabricar a mano, así que el servidor vuelve a verificar.
 */
final class ElegirRolActivo
{
    private const CLAVE_SESION = 'sec_rol_activo_id';

    /**
     * @throws RolNoAsignado si `$idRolDeseado` no está entre los roles vivos
     *                       de `$usuario` (nunca lo tuvo, se lo revocaron, o
     *                       el rol/la asignación están desactivados).
     */
    public function ejecutar(SecUser $usuario, int $idRolDeseado): SecRole
    {
        $rol = $this->rolVivoDelUsuario($usuario, $idRolDeseado);

        if ($rol === null) {
            throw RolNoAsignado::paraUsuario($usuario->id, $idRolDeseado);
        }

        Session::put(self::CLAVE_SESION, $rol->id);

        $this->registrarUltimoRol($usuario, $rol);

        return $rol;
    }

    /**
     * Registra `sec_user_preferencia.ultimo_rol_id` (badge "ÚLTIMO USADO" de
     * la pantalla de selección, quinta vuelta — maqueta 5c). Va acá y no en
     * cada llamador porque esta clase ya es el ÚNICO punto que activa roles
     * (ver docblock de la clase) — cualquier activación, venga del login, del
     * selector o del middleware, ES el "último rol usado". Puramente
     * informativo: nunca gobierna permisos ni se revalida acá.
     */
    private function registrarUltimoRol(SecUser $usuario, SecRole $rol): void
    {
        $preferencia = SecUserPreferencia::query()->firstOrNew(['user_id' => $usuario->id]);

        if ($preferencia->ultimo_rol_id === $rol->id) {
            return;
        }

        $preferencia->ultimo_rol_id = $rol->id;

        if (! $preferencia->exists) {
            $preferencia->created_by = $usuario->id;
        }
        $preferencia->updated_by = $usuario->id;

        $preferencia->save();
    }

    private function rolVivoDelUsuario(SecUser $usuario, int $idRol): ?SecRole
    {
        /** @var SecRole|null */
        return SecRole::query()
            ->select('sec_role.*')
            ->join('sec_user_role', 'sec_user_role.id_role', '=', 'sec_role.id')
            ->where('sec_user_role.id_user', $usuario->id)
            ->where('sec_role.id', $idRol)
            ->whereNull('sec_user_role.deleted_at')
            ->where('sec_role.state', true)
            ->first();
    }
}
