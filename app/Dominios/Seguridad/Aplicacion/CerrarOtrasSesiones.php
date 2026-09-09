<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cierra las sesiones de OTROS dispositivos del mismo usuario tras un cambio
 * de contraseña propio (tarea 66, `ActualizarPerfilPropio`) — la sesión que
 * pide el cambio sigue viva, todas las demás dejan de servir de inmediato.
 *
 * `Auth::guard($guard)->logoutOtherDevices()` (que igual se invoca, por ser
 * el mecanismo estándar de Laravel) no alcanza SOLO acá: recién tiene efecto
 * junto al middleware `AuthenticateSession`, que este proyecto no cablea —
 * y no podría cablearse igual para los dos guards: ese middleware compara
 * siempre contra el guard **por defecto** (`AUTH_GUARD=interno`), así que en
 * el guard `cliente` del portal no haría nada nunca. Este caso de uso, en
 * cambio, borra directo la fila de `sessions` (driver `database`,
 * `.env.example`) de cualquier sesión viva que loguee a ESTE usuario bajo
 * ESTE guard — funciona igual para `interno` y para `cliente`.
 *
 * La clave de sesión que Laravel usa para el usuario logueado
 * (`SessionGuard::getName()`) YA incluye el nombre del guard
 * (`login_<guard>_<sha1>`), así que decodificar el payload y comparar esa
 * clave es correcto sin ambigüedad — a diferencia de `sessions.user_id`
 * (columna del framework, sin FK), que el `DatabaseSessionHandler` completa
 * siempre desde el guard por defecto y por eso queda en NULL en toda sesión
 * del portal.
 *
 * No hace nada si el driver de sesión no es `database` (otros drivers no
 * tienen esta tabla que barrer) — ahí el rehash de contraseña queda como
 * única defensa, documentado en runs/66.md.
 */
final class CerrarOtrasSesiones
{
    public function ejecutar(SecUser $usuario, string $guard, string $idSesionActual, ?string $passwordNueva): void
    {
        if ($passwordNueva !== null) {
            /** @var SessionGuard $guardEnUso */
            $guardEnUso = Auth::guard($guard);
            $guardEnUso->logoutOtherDevices($passwordNueva);
        }

        if (config('session.driver') !== 'database') {
            return;
        }

        /** @var SessionGuard $guardParaClave */
        $guardParaClave = Auth::guard($guard);
        $claveLogin = $guardParaClave->getName();
        $identidad = $usuario->getAuthIdentifier();
        $serializacion = config('session.serialization', 'php');
        $conexion = config('session.connection');
        $tabla = config('session.table', 'sessions');

        DB::connection($conexion)
            ->table($tabla)
            ->where('id', '!=', $idSesionActual)
            ->get(['id', 'payload'])
            ->each(function (object $fila) use ($claveLogin, $identidad, $serializacion, $conexion, $tabla): void {
                $crudo = base64_decode((string) $fila->payload, true) ?: '';

                $datos = $serializacion === 'json'
                    ? (json_decode($crudo, true) ?: [])
                    : (@unserialize($crudo) ?: []);

                if (($datos[$claveLogin] ?? null) === $identidad) {
                    DB::connection($conexion)->table($tabla)->where('id', $fila->id)->delete();
                }
            });
    }
}
