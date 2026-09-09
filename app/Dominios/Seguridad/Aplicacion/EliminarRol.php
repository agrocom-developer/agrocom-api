<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\RolProtegido;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRolePermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Illuminate\Support\Facades\DB;

/**
 * Baja lógica de un rol (ADR 0007: soft delete, nunca `DELETE` físico).
 *
 * Tres guardas, y las tres son sobre cosas que la base aceptaría sin
 * chistar:
 *
 * 1. NO CON USUARIOS DETRÁS. La FK no lo impide (el rol sigue existiendo,
 *    solo con `deleted_at`), así que quien lo tuviera asignado quedaría con
 *    un rol fantasma: elegible en el selector hasta que `ResolverRolActivo`
 *    lo rechaza, y sin ninguna pantalla que explique por qué. Se exige
 *    reasignar antes.
 * 2. NO EL ROL PROPIO. Darte de baja el rol con el que estás operando te deja
 *    afuera en el request siguiente.
 * 3. NO LA ÚLTIMA LLAVE. Si es el único rol vivo que puede administrar
 *    permisos, darlo de baja cierra la pantalla de roles para todos, sin
 *    vuelta desde el panel.
 *
 * Al dar de baja, sus otorgamientos de permiso se dan de baja también. No es
 * limpieza cosmética: {@see CatalogoDePermisos} decide "quién sostiene este
 * permiso" mirando el pivote, y filas vivas colgando de un rol muerto harían
 * creer que un permiso tiene portador cuando no lo tiene nadie que pueda
 * iniciar sesión.
 */
final class EliminarRol
{
    private const PERMISO_ELIMINAR = 'seguridad.rol.eliminar';

    private const PERMISO_LLAVE = 'seguridad.rol.asignar_permiso';

    public function __construct(private readonly CatalogoDePermisos $catalogo) {}

    /**
     * @param  int|null  $idRolActivo  Ver la nota de {@see AsignarPermisosRol::ejecutar()}.
     *
     * @throws PermisoDenegado
     * @throws RolProtegido
     */
    public function ejecutar(SecUser $actor, SecRole $rol, ?int $idRolActivo): void
    {
        if (! $this->actorTienePermiso($actor, $idRolActivo, self::PERMISO_ELIMINAR)) {
            throw PermisoDenegado::porFaltaDePermiso(self::PERMISO_ELIMINAR);
        }

        if ($idRolActivo !== null && $rol->id === $idRolActivo) {
            throw RolProtegido::porSerElRolActivo($rol->name);
        }

        $usuarios = $this->cuentasVivasCon($rol->id);

        if ($usuarios > 0) {
            throw RolProtegido::porTenerUsuarios($rol->name, $usuarios);
        }

        if ($this->catalogo->idsRolVivoConPermiso(self::PERMISO_LLAVE) === [$rol->id]) {
            throw RolProtegido::porUltimaLlave(self::PERMISO_LLAVE);
        }

        DB::transaction(function () use ($rol, $actor): void {
            SecRolePermission::query()
                ->where('id_role', $rol->id)
                ->get()
                ->each(function (SecRolePermission $pivote) use ($actor): void {
                    // save() antes de delete(): `updated_by` no es fillable y
                    // `runSoftDelete()` no lo persistiría (invariante 9).
                    $pivote->updated_by = $actor->id;
                    $pivote->save();
                    $pivote->delete();
                });

            $rol->updated_by = $actor->id;
            $rol->save();
            $rol->delete();
        });
    }

    /**
     * Asignaciones vivas del rol a cuentas vivas. Las dos condiciones hacen
     * falta: un usuario dado de baja conserva sus filas en `sec_user_role`, y
     * contarlo bloquearía para siempre la baja de un rol que en la práctica
     * ya no usa nadie.
     */
    private function cuentasVivasCon(int $idRol): int
    {
        return SecUserRole::query()
            ->where('id_role', $idRol)
            ->whereIn('id_user', SecUser::query()->select('id'))
            ->count();
    }

    private function actorTienePermiso(SecUser $actor, ?int $idRolActivo, string $codigo): bool
    {
        return $idRolActivo !== null
            ? $actor->tienePermisoEnRol($codigo, $idRolActivo)
            : $actor->tienePermiso($codigo);
    }
}
