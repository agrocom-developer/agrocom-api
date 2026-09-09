<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\UsuarioDuplicado;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso único para alta y edición de usuarios internos con sus roles
 * (HU-01). Cubre alta y edición con la MISMA guarda de permisos a propósito
 * (diseño `modulos-roles` §3): si solo se protegiera "crear", un encargado de
 * operaciones lograría asignarse el rol `dueno` editando después de la
 * creación — la guarda tiene que estar en un único lugar que ambos caminos
 * atraviesen.
 *
 * No hay controlador ni ruta HTTP en HU-01 (es puro backend, ADR 0008): lo
 * invocan directo los tests y, más adelante, el endpoint/panel de HU-02/HU-03
 * — la lógica vive acá una sola vez, nunca duplicada en un controller.
 */
final class AsignarRolesUsuario
{
    private const PERMISO_CREAR = 'seguridad.usuario.crear';

    private const PERMISO_EDITAR = 'seguridad.usuario.editar';

    private const PERMISO_ROL_DUENO = 'seguridad.usuario.asignar_rol_dueno';

    /**
     * @param  SecUser  $actor  Quién ejecuta la acción: define tanto el permiso
     *                          exigido para entrar como la autoría de las filas
     *                          que se crean/revocan (no depende de `Auth::id()`
     *                          porque el caso de uso no asume contexto HTTP).
     * @param  int|null  $usuarioId  `null` = alta; con valor = edición del
     *                               usuario existente.
     * @param  string|null  $password  `null` en edición conserva el hash
     *                                 vigente — no se reescribe la contraseña
     *                                 si no se envía una nueva.
     * @param  string|null  $email  Correo de la cuenta (tarea 66, ampliación
     *                              ADR 0004 9/9/2026) — de la cuenta, no de
     *                              la persona operativa. `null` = sin correo.
     * @param  list<int>  $roleIds  Set completo y definitivo de roles deseados,
     *                              no un delta: los que falten respecto a los
     *                              actuales se revocan (soft delete) y los
     *                              nuevos se asignan, así "editar" nunca deja
     *                              un rol huérfano por omisión.
     * @param  int|null  $idRolActivo  Rol activo de la sesión del actor
     *                                 (`session('sec_rol_activo_id')`), sin
     *                                 default: quien invoca desde el panel
     *                                 SIEMPRE lo pasa (evaluación por
     *                                 `tienePermisoEnRol()`, nunca la unión);
     *                                 `null` es la elección explícita de un
     *                                 llamador legítimamente sin sesión
     *                                 (seeders, comandos, tests directos del
     *                                 caso de uso), que cae en
     *                                 `tienePermiso()` (unión) a propósito —
     *                                 nunca un valor por omisión que nadie
     *                                 pidió.
     *
     * @throws PermisoDenegado si al actor le falta el permiso de entrada
     *                         (`crear`/`editar`) o, cuando algún rol del
     *                         payload que cambia de estado lleva asignado el
     *                         permiso `asignar_rol_dueno`, ese mismo permiso.
     * @throws UsuarioDuplicado si el `username`, el `email` o la `persona_id`
     *                          ya pertenecen a otra cuenta viva.
     */
    public function ejecutar(
        SecUser $actor,
        ?int $usuarioId,
        string $username,
        ?string $email,
        ?string $password,
        string $name,
        TipoUsuario $type,
        ?int $personaId,
        ?int $contratoId,
        array $roleIds,
        ?int $idRolActivo,
    ): SecUser {
        $esAlta = $usuarioId === null;

        // (int) explícito: normaliza el payload sin importar si el llamador
        // sacó los IDs de un pluck() de Postgres (numeric string) o de
        // sqlite (int nativo) — evita que las comparaciones estrictas de
        // más abajo dependan del driver de quien invoca el caso de uso.
        /** @var list<int> $roleIds */
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        $this->verificarPermisoDeEntrada($actor, $idRolActivo, $esAlta);

        return DB::transaction(function () use (
            $actor,
            $usuarioId,
            $username,
            $email,
            $password,
            $name,
            $type,
            $personaId,
            $contratoId,
            $roleIds,
            $idRolActivo,
            $esAlta,
        ): SecUser {
            $usuario = $esAlta
                ? new SecUser
                : SecUser::query()->findOrFail($usuarioId);

            $rolesActuales = $esAlta ? [] : $usuario->idsDeRoles();

            $this->verificarGuardaRolDueno($actor, $idRolActivo, $rolesActuales, $roleIds);

            $usuario->name = $name;
            $usuario->username = $username;
            $usuario->email = $email;
            $usuario->type = $type;
            $usuario->persona_id = $personaId;
            $usuario->contrato_id = $contratoId;

            if ($password !== null) {
                $usuario->password = $password;
            }

            if (! $usuario->exists) {
                $usuario->created_by = $actor->id;
            }
            $usuario->updated_by = $actor->id;

            try {
                $usuario->save();
            } catch (QueryException $excepcion) {
                $this->relanzarComoDuplicado($excepcion, $username, $email, $personaId);
            }

            $this->sincronizarRoles($usuario, $rolesActuales, $roleIds, $actor);

            return $usuario->refresh();
        });
    }

    private function verificarPermisoDeEntrada(SecUser $actor, ?int $idRolActivo, bool $esAlta): void
    {
        $codigo = $esAlta ? self::PERMISO_CREAR : self::PERMISO_EDITAR;

        if (! $this->actorTienePermiso($actor, $idRolActivo, $codigo)) {
            throw PermisoDenegado::porFaltaDePermiso($codigo);
        }
    }

    /**
     * Guarda genérica por permiso de rol, no por nombre de rol: cualquier rol
     * del payload que cambie de estado (se asigna o se quita) y que a su vez
     * tenga otorgado `asignar_rol_dueno` en el catálogo (`sec_role_permission`)
     * exige que el actor también tenga ese permiso — evaluado en su rol
     * activo si lo hay, nunca por el nombre literal `dueno` (el rol que hoy
     * lo tiene podría renombrarse, o el permiso otorgarse a otro rol nuevo,
     * sin que esta guarda deje de aplicar).
     *
     * @param  list<int>  $rolesActuales
     * @param  list<int>  $rolesDeseados
     */
    private function verificarGuardaRolDueno(SecUser $actor, ?int $idRolActivo, array $rolesActuales, array $rolesDeseados): void
    {
        $rolesAfectados = array_values(array_unique(array_merge(
            array_diff($rolesDeseados, $rolesActuales),
            array_diff($rolesActuales, $rolesDeseados),
        )));

        if ($rolesAfectados === [] || $this->rolesConPermiso($rolesAfectados, self::PERMISO_ROL_DUENO) === []) {
            return;
        }

        if (! $this->actorTienePermiso($actor, $idRolActivo, self::PERMISO_ROL_DUENO)) {
            throw PermisoDenegado::porFaltaDePermiso(self::PERMISO_ROL_DUENO);
        }
    }

    /**
     * Único punto de evaluación de permiso de este caso de uso: con rol
     * activo, SIEMPRE `tienePermisoEnRol()`; sin él (llamador legítimamente
     * sin sesión), la unión de `tienePermiso()` — nunca al revés por default
     * (ADR 0004, extensión 27/8/2026, punto 5).
     */
    private function actorTienePermiso(SecUser $actor, ?int $idRolActivo, string $codigo): bool
    {
        return $idRolActivo !== null
            ? $actor->tienePermisoEnRol($codigo, $idRolActivo)
            : $actor->tienePermiso($codigo);
    }

    /**
     * @param  list<int>  $idsRol
     * @return list<int> subconjunto de `$idsRol` que tiene otorgado
     *                   `$codigoPermiso` en el catálogo, vivo en ambos
     *                   extremos del pivote (`sec_role_permission`,
     *                   `sec_permission.state`).
     */
    private function rolesConPermiso(array $idsRol, string $codigoPermiso): array
    {
        return DB::table('sec_role_permission')
            ->join('sec_permission', 'sec_permission.id', '=', 'sec_role_permission.id_permission')
            ->whereIn('sec_role_permission.id_role', $idsRol)
            ->whereNull('sec_role_permission.deleted_at')
            ->whereNull('sec_permission.deleted_at')
            ->where('sec_permission.code', $codigoPermiso)
            ->where('sec_permission.state', true)
            ->pluck('sec_role_permission.id_role')
            ->map(static fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $rolesActuales
     * @param  list<int>  $rolesDeseados
     */
    private function sincronizarRoles(SecUser $usuario, array $rolesActuales, array $rolesDeseados, SecUser $actor): void
    {
        foreach (array_diff($rolesDeseados, $rolesActuales) as $idRol) {
            $pivote = new SecUserRole([
                'id_user' => $usuario->id,
                'id_role' => $idRol,
            ]);
            $pivote->created_by = $actor->id;
            $pivote->updated_by = $actor->id;
            $pivote->save();
        }

        $aRevocar = array_diff($rolesActuales, $rolesDeseados);

        if ($aRevocar === []) {
            return;
        }

        SecUserRole::query()
            ->where('id_user', $usuario->id)
            ->whereIn('id_role', $aRevocar)
            ->get()
            ->each(function (SecUserRole $pivote) use ($actor): void {
                // Asignación directa + save() antes de delete(): `updated_by`
                // no es fillable (autoría nunca es mass-assignable) y
                // `runSoftDelete()` solo persiste deleted_at/updated_at, así
                // que necesita su propio guardado para quedar auditado
                // (invariante 9) antes del soft delete.
                $pivote->updated_by = $actor->id;
                $pivote->save();
                $pivote->delete();
            });
    }

    /**
     * Traduce la violación de un índice único parcial de `sec_user` a una
     * excepción de dominio legible — nunca deja propagarse el 500 crudo del
     * motor de base de datos (regla de negocio, no solo estilo).
     *
     * El formato del mensaje de error difiere por driver: Postgres nombra el
     * índice (`sec_user_username_unico`); SQLite (el motor de los tests,
     * `phpunit.xml`) nombra tabla.columna (`sec_user.username`). Se
     * comprueban ambos formatos para que la traducción funcione igual en los
     * dos entornos — cualquier otra violación (FK, NOT NULL) no calza con
     * ninguno de los dos y se relanza intacta.
     *
     * @throws UsuarioDuplicado si la violación corresponde a `username`, a
     *                          `email` o a `persona_id`.
     * @throws QueryException si la violación no es una de las contempladas.
     */
    private function relanzarComoDuplicado(QueryException $excepcion, string $username, ?string $email, ?int $personaId): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'sec_user_username_unico') || str_contains($mensaje, 'sec_user.username')) {
            throw UsuarioDuplicado::porUsername($username);
        }

        if ($email !== null
            && (str_contains($mensaje, 'sec_user_email_unico') || str_contains($mensaje, 'sec_user.email'))
        ) {
            throw UsuarioDuplicado::porEmail($email);
        }

        if ($personaId !== null
            && (str_contains($mensaje, 'sec_user_persona_id_unico') || str_contains($mensaje, 'sec_user.persona_id'))
        ) {
            throw UsuarioDuplicado::porPersona($personaId);
        }

        throw $excepcion;
    }
}
