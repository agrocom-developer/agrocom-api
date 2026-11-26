<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\RolProtegido;
use App\Dominios\Seguridad\Dominio\PermisosReservados;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Edición de un rol del catálogo: descripción y estado. Los permisos NO se tocan
 * acá — eso es {@see AsignarPermisosRol}, que tiene sus propias guardas.
 *
 * El panel tiene dos roles, `administrador` y `operador` (ADR 0046, punto 4), y
 * son fijos: no se crean roles nuevos ni se cambia el nombre de estos (el código
 * los nombra), y el administrador no se desactiva. Una cuenta de panel tiene un
 * solo rol, así que un tercer rol no tendría a quién asignarse.
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
     * @param  SecRole|null  $rol  `null` = alta, que se rechaza (el catálogo es fijo); con valor = edición.
     * @param  int|null  $idRolActivo  Ver la nota de {@see AsignarPermisosRol::ejecutar()}.
     *
     * @throws PermisoDenegado si al actor le falta `crear`/`editar`.
     * @throws RolProtegido si es un alta, un cambio de nombre o la desactivación
     *                      del administrador; o si desactivar dejaría al actor
     *                      afuera o al sistema sin quién administre permisos.
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

        // Catálogo fijo: solo se edita la descripción y el estado de un rol del panel, nunca su nombre.
        if ($rol === null || ! PermisosReservados::esRolDelPanel((string) $rol->name) || $nombre !== $rol->name) {
            throw RolProtegido::porCatalogoFijo();
        }

        if (! $activo && PermisosReservados::admiteElRol((string) $rol->name)) {
            throw RolProtegido::porRolDelPanel((string) $rol->name);
        }

        if ($rol->state && ! $activo) {
            $this->verificarQueSePuedeDesactivar($rol, $idRolActivo);
        }

        $rol->description = $descripcion;
        $rol->state = $activo;
        $rol->updated_by = $actor->id;
        $rol->save();

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

    private function actorTienePermiso(SecUser $actor, ?int $idRolActivo, string $codigo): bool
    {
        return $idRolActivo !== null
            ? $actor->tienePermisoEnRol($codigo, $idRolActivo)
            : $actor->tienePermiso($codigo);
    }
}
