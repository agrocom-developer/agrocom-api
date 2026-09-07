<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\RolDuplicado;
use App\Dominios\Seguridad\Dominio\Excepciones\RolProtegido;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Database\QueryException;

/**
 * Alta y edición de un rol del catálogo: nombre, descripción y estado. Los
 * permisos NO se tocan acá — eso es {@see AsignarPermisosRol}, que tiene sus
 * propias guardas. Un rol nuevo nace sin ninguno.
 *
 * Alta y edición comparten caso de uso a propósito, mismo criterio que
 * {@see AsignarRolesUsuario}: si la guarda de estado viviera solo en el alta,
 * se la esquivaría creando el rol y editándolo después.
 *
 * Desactivar un rol (`state = false`) no es un cambio cosmético: deja de ser
 * elegible al iniciar sesión y deja de contar como portador de sus permisos
 * (ver {@see CatalogoDePermisos}). Por eso lleva las mismas dos guardas que
 * la baja:
 *
 * 1. No desactivar el rol con el que estás operando — `ResolverRolActivo`
 *    revalida en cada request y te dejaría afuera en el clic siguiente.
 * 2. No desactivar el último rol vivo que puede administrar permisos: sería
 *    tirar la llave adentro de la casa por la puerta de al lado.
 */
final class GuardarRol
{
    private const PERMISO_CREAR = 'seguridad.rol.crear';

    private const PERMISO_EDITAR = 'seguridad.rol.editar';

    private const PERMISO_LLAVE = 'seguridad.rol.asignar_permiso';

    public function __construct(private readonly CatalogoDePermisos $catalogo) {}

    /**
     * @param  SecRole|null  $rol  `null` = alta; con valor = edición.
     * @param  int|null  $idRolActivo  Ver la nota de {@see AsignarPermisosRol::ejecutar()}.
     *
     * @throws PermisoDenegado si al actor le falta `crear`/`editar`.
     * @throws RolProtegido si la desactivación dejaría al actor afuera o al
     *                      sistema sin quién administre permisos.
     * @throws RolDuplicado si el nombre ya pertenece a otro rol (vivo o dado
     *                      de baja: `sec_role.name` es UNIQUE plano).
     */
    public function ejecutar(
        SecUser $actor,
        ?SecRole $rol,
        string $nombre,
        string $descripcion,
        bool $activo,
        ?int $idRolActivo,
    ): SecRole {
        $esAlta = $rol === null;
        $codigo = $esAlta ? self::PERMISO_CREAR : self::PERMISO_EDITAR;

        if (! $this->actorTienePermiso($actor, $idRolActivo, $codigo)) {
            throw PermisoDenegado::porFaltaDePermiso($codigo);
        }

        if (! $esAlta && $rol->state && ! $activo) {
            $this->verificarQueSePuedeDesactivar($rol, $idRolActivo);
        }

        $rol ??= new SecRole;
        $rol->name = $nombre;
        $rol->description = $descripcion;
        $rol->state = $activo;

        if (! $rol->exists) {
            $rol->created_by = $actor->id;
        }
        $rol->updated_by = $actor->id;

        try {
            $rol->save();
        } catch (QueryException $excepcion) {
            $this->relanzarComoDuplicado($excepcion, $nombre);
        }

        return $rol->refresh();
    }

    /**
     * @throws RolProtegido
     */
    private function verificarQueSePuedeDesactivar(SecRole $rol, ?int $idRolActivo): void
    {
        if ($idRolActivo !== null && $rol->id === $idRolActivo) {
            throw RolProtegido::porSerElRolActivo($rol->name);
        }

        $conLaLlave = $this->catalogo->idsRolVivoConPermiso(self::PERMISO_LLAVE);

        if ($conLaLlave === [$rol->id]) {
            throw RolProtegido::porUltimaLlave(self::PERMISO_LLAVE);
        }
    }

    /**
     * Traduce la violación del índice único de `sec_role.name` a una
     * excepción de dominio legible — nunca deja propagarse el 500 crudo del
     * motor (mismo criterio y mismo problema de portabilidad que
     * {@see AsignarRolesUsuario::relanzarComoDuplicado()}: Postgres nombra el
     * índice, SQLite nombra tabla.columna, y los tests corren sobre SQLite).
     *
     * @throws RolDuplicado
     * @throws QueryException si la violación no es la contemplada.
     */
    private function relanzarComoDuplicado(QueryException $excepcion, string $nombre): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'sec_role_name_unique') || str_contains($mensaje, 'sec_role.name')) {
            throw RolDuplicado::porNombre($nombre);
        }

        throw $excepcion;
    }

    private function actorTienePermiso(SecUser $actor, ?int $idRolActivo, string $codigo): bool
    {
        return $idRolActivo !== null
            ? $actor->tienePermisoEnRol($codigo, $idRolActivo)
            : $actor->tienePermiso($codigo);
    }
}
