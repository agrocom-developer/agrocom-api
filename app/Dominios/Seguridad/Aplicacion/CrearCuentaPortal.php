<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\ContratoNoDisponibleParaPortal;
use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\UsuarioDuplicado;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso único para alta y edición de cuentas de portal (`type =
 * cliente`, tarea 65, HU-41) — aparte de {@see AsignarRolesUsuario} a
 * propósito: una cuenta de portal nunca pasa por `sec_user_role` ni tiene
 * rol activo, así que mezclarla en el caso de uso de cuentas internas
 * obligaría a esa guarda de roles a bifurcar por `type` en vez de,
 * simplemente, no existir para este camino. Acá la única guarda de negocio
 * es el contrato: que exista y esté vigente.
 *
 * Permiso adicional (`seguridad.usuario.portal`, solo `dueno` y
 * `encargado_operaciones` en el catálogo por ahora): crear o editar una
 * cuenta de portal exige ESE permiso además del genérico
 * `seguridad.usuario.crear`/`.editar` — un rol con el genérico pero sin el
 * de portal administra cuentas internas y no toca el portal del cliente.
 */
final class CrearCuentaPortal
{
    private const PERMISO_CREAR = 'seguridad.usuario.crear';

    private const PERMISO_EDITAR = 'seguridad.usuario.editar';

    private const PERMISO_PORTAL = 'seguridad.usuario.portal';

    /**
     * `com_contratos.estado` es de otro módulo (Comercial): se lee por
     * `DB::table`, nunca importando su Eloquent ni su enum de dominio
     * (ADR 0003 regla 3, mismo criterio que `personasDisponibles()` en
     * `UsuariosController`) — el valor 'vigente' es el de
     * `App\Dominios\Comercial\Dominio\EstadoContrato::Vigente->value`.
     */
    private const ESTADO_CONTRATO_VIGENTE = 'vigente';

    /**
     * @param  SecUser  $actor  Igual criterio que {@see AsignarRolesUsuario}:
     *                          define el permiso exigido y la autoría de la
     *                          fila.
     * @param  int|null  $usuarioId  `null` = alta; con valor = edición de una
     *                               cuenta `cliente` existente.
     * @param  string|null  $password  `null` en edición conserva el hash
     *                                 vigente.
     * @param  int|null  $idRolActivo  Mismo criterio que
     *                                 {@see AsignarRolesUsuario}: con sesión
     *                                 de panel siempre se pasa
     *                                 (`tienePermisoEnRol()`); `null` es la
     *                                 elección explícita de un llamador sin
     *                                 sesión (seeders, tests directos), que
     *                                 cae en `tienePermiso()` (unión).
     *
     * @throws PermisoDenegado si al actor le falta `crear`/`editar` o
     *                         `seguridad.usuario.portal`.
     * @throws ContratoNoDisponibleParaPortal si el contrato no existe, está
     *                                        borrado o no está vigente.
     * @throws UsuarioDuplicado si el `username` ya pertenece a otra cuenta
     *                          viva.
     */
    public function ejecutar(
        SecUser $actor,
        ?int $usuarioId,
        string $username,
        ?string $password,
        string $name,
        int $contratoId,
        ?int $idRolActivo,
    ): SecUser {
        $esAlta = $usuarioId === null;

        $this->verificarPermisos($actor, $idRolActivo, $esAlta);
        $this->verificarContratoVigente($contratoId);

        return DB::transaction(function () use ($actor, $usuarioId, $username, $password, $name, $contratoId, $esAlta): SecUser {
            $usuario = $esAlta
                ? new SecUser
                : SecUser::query()->where('type', TipoUsuario::Cliente)->findOrFail($usuarioId);

            $usuario->name = $name;
            $usuario->username = $username;
            $usuario->type = TipoUsuario::Cliente;
            $usuario->persona_id = null;
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
                $this->relanzarComoDuplicado($excepcion, $username);
            }

            return $usuario->refresh();
        });
    }

    private function verificarPermisos(SecUser $actor, ?int $idRolActivo, bool $esAlta): void
    {
        $codigoEntrada = $esAlta ? self::PERMISO_CREAR : self::PERMISO_EDITAR;

        if (! $this->actorTienePermiso($actor, $idRolActivo, $codigoEntrada)) {
            throw PermisoDenegado::porFaltaDePermiso($codigoEntrada);
        }

        if (! $this->actorTienePermiso($actor, $idRolActivo, self::PERMISO_PORTAL)) {
            throw PermisoDenegado::porFaltaDePermiso(self::PERMISO_PORTAL);
        }
    }

    /** Mismo criterio de evaluación que {@see AsignarRolesUsuario}. */
    private function actorTienePermiso(SecUser $actor, ?int $idRolActivo, string $codigo): bool
    {
        return $idRolActivo !== null
            ? $actor->tienePermisoEnRol($codigo, $idRolActivo)
            : $actor->tienePermiso($codigo);
    }

    private function verificarContratoVigente(int $contratoId): void
    {
        $existe = DB::table('com_contratos')
            ->where('id', $contratoId)
            ->whereNull('deleted_at')
            ->where('estado', self::ESTADO_CONTRATO_VIGENTE)
            ->exists();

        if (! $existe) {
            throw ContratoNoDisponibleParaPortal::porId($contratoId);
        }
    }

    /**
     * Mismo criterio que `AsignarRolesUsuario::relanzarComoDuplicado()`: una
     * cuenta de portal no tiene `persona_id`, así que el único índice único
     * que puede violar es el de `username`.
     *
     * @throws UsuarioDuplicado si la violación corresponde a `username`.
     * @throws QueryException si la violación no es esa.
     */
    private function relanzarComoDuplicado(QueryException $excepcion, string $username): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'sec_user_username_unico') || str_contains($mensaje, 'sec_user.username')) {
            throw UsuarioDuplicado::porUsername($username);
        }

        throw $excepcion;
    }
}
