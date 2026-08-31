<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\RolNoAsignado;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Emite el token con el que un dispositivo de campo opera sin volver a
 * loguearse (HU-03). La verificación de `username` + password NO vive acá: es
 * plumbing de autenticación del controlador, igual que en
 * {@see IniciarSesionPanel}. Lo que sí es regla de negocio, y por eso está
 * acá, es con qué rol queda operando el dispositivo y qué pasa con el token
 * anterior del mismo teléfono.
 *
 * **Rol activo del token** (invariante 10 de CLAUDE.md): el token es la
 * sesión de la app, así que lleva un único rol, no la unión de los roles del
 * usuario. Mismo criterio que el login del panel:
 * - Rol pedido explícitamente → se valida contra los roles vivos y se usa.
 * - Sin rol pedido y un único rol vivo → se usa ese, sin preguntar.
 * - Sin rol pedido y cero o dos+ roles vivos → no se emite nada; el
 *   controlador pide la selección ({@see ResultadoEmisionToken}). Nunca un
 *   fallback silencioso.
 *
 * **Un solo token vivo por dispositivo**: reintentar el login desde el mismo
 * teléfono revoca el token anterior y emite uno nuevo, en una transacción.
 * No se puede "devolver el mismo": el valor en claro solo existió en la
 * respuesta de emisión. Es la contracara del índice parcial único
 * `(user_id, uuid_dispositivo) WHERE deleted_at IS NULL` — un login repetido
 * no acumula credenciales vivas que después haya que revocar de a una.
 */
final class EmitirTokenDispositivo
{
    public function __construct(private readonly ListarRolesDisponibles $listarRolesDisponibles) {}

    /**
     * @param  string  $uuidDispositivo  UUID generado en el dispositivo, igual
     *                                   que `uuid_cliente` en los registros que nacen en campo
     *                                   (invariante 1).
     * @param  int|null  $idRolDeseado  Rol con el que el usuario quiere operar
     *                                  en este dispositivo; `null` deja que se resuelva solo si
     *                                  tiene un único rol vivo.
     *
     * @throws RolNoAsignado si `$idRolDeseado` no está entre los roles vivos
     *                       del usuario (nunca lo tuvo, se lo revocaron, o el
     *                       rol está desactivado en el catálogo).
     */
    public function ejecutar(
        SecUser $usuario,
        string $uuidDispositivo,
        ?string $nombreDispositivo = null,
        ?int $idRolDeseado = null,
    ): ResultadoEmisionToken {
        $idsRolesVivos = $usuario->idsDeRolesActivos();

        if ($idRolDeseado !== null && ! in_array($idRolDeseado, $idsRolesVivos, true)) {
            throw RolNoAsignado::paraUsuario($usuario->id, $idRolDeseado);
        }

        $idRol = $idRolDeseado ?? (count($idsRolesVivos) === 1 ? $idsRolesVivos[0] : null);

        if ($idRol === null) {
            return ResultadoEmisionToken::requiereSeleccionDeRol(
                $this->listarRolesDisponibles->ejecutar($usuario),
            );
        }

        $secreto = $this->generarSecreto();

        $token = DB::transaction(function () use ($usuario, $uuidDispositivo, $nombreDispositivo, $idRol, $secreto): SecTokenDispositivo {
            $this->revocarTokenVivoDelDispositivo($usuario, $uuidDispositivo);

            $token = new SecTokenDispositivo([
                'user_id' => $usuario->id,
                'role_id' => $idRol,
                'uuid_dispositivo' => $uuidDispositivo,
                'nombre_dispositivo' => $nombreDispositivo,
                'token' => hash('sha256', $secreto),
                // El token no caduca por tiempo: la app de campo puede estar
                // días sin señal y tiene que seguir operando (CA "sesión
                // persistente offline"). Deja de valer cuando lo revocan.
                'expires_at' => null,
            ]);

            // La emisión ocurre en el login de la app, donde todavía no hay
            // usuario autenticado: `RegistraAutoria` no tiene de dónde sacar
            // el autor, así que se asigna explícitamente. El usuario se emite
            // el token a sí mismo.
            $token->created_by = $usuario->id;
            $token->updated_by = $usuario->id;
            $token->save();

            return $token;
        });

        return ResultadoEmisionToken::conToken($token, $token->id.'|'.$secreto);
    }

    /**
     * Mismo formato que `HasApiTokens::generateTokenString()` de Sanctum
     * (prefijo configurable + 40 caracteres aleatorios + checksum crc32b, que
     * es lo que permite a los escáneres de secretos reconocer un token
     * filtrado). No se reusa el método del trait porque `createToken()` está
     * sellado en {@see SecUser} y no queremos que este caso de uso dependa de
     * instanciar el modelo de usuario solo para generar una cadena.
     */
    private function generarSecreto(): string
    {
        $entropia = Str::random(40);

        return sprintf('%s%s%s', config('sanctum.token_prefix', ''), $entropia, hash('crc32b', $entropia));
    }

    private function revocarTokenVivoDelDispositivo(SecUser $usuario, string $uuidDispositivo): void
    {
        $anterior = SecTokenDispositivo::query()
            ->where('user_id', $usuario->id)
            ->where('uuid_dispositivo', $uuidDispositivo)
            ->first();

        if ($anterior === null) {
            return;
        }

        $anterior->updated_by = $usuario->id;
        $anterior->save();
        $anterior->delete();
    }
}
