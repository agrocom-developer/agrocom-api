<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\RolProtegido;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;

/**
 * Alta y edición de un rol del panel: nombre, descripción y estado. Los
 * permisos NO se tocan acá — eso es {@see AsignarPermisosRol}, que tiene sus
 * propias guardas; un rol nuevo nace sin ningún permiso.
 *
 * El panel parte de dos roles sembrados, `administrador` (id 1) y `operador`, y
 * el administrador puede crear más, editarlos y —salvo el suyo— eliminarlos
 * ({@see EliminarRol}). Una cuenta de panel tiene un solo rol, así que cada rol
 * nuevo es un perfil que se asigna desde Usuarios. Reglas:
 *
 * 1. EL ROL DEL SISTEMA NO SE TOCA. El administrador (id 1) conserva su nombre y
 *    su estado: solo se edita su descripción. El código lo nombra (sus permisos
 *    reservados, la guarda de «asignar rol administrador») y desactivarlo cerraría
 *    el panel entero.
 * 2. SOLO ROLES DEL PANEL. Los de ámbito `app` (transitorios hasta A5) no se
 *    crean, editan ni eliminan desde acá.
 * 3. NOMBRE ÚNICO sin distinguir mayúsculas, también frente a un rol eliminado
 *    (el índice único de `sec_role.name` no libera el nombre al dar de baja). Así
 *    nadie crea «Administrador» para parecerse al rol del sistema.
 *
 * Desactivar un rol (`state = false`) no es un cambio cosmético: deja de ser
 * elegible al iniciar sesión y deja de contar como portador de sus permisos
 * (ver {@see CatalogoDePermisos}). Por eso lleva las mismas dos guardas que la
 * baja:
 *
 * 1. No desactivar el rol con el que estás operando — `ResolverRolActivo`
 *    revalida en cada request y te dejaría afuera en el clic siguiente.
 * 2. No desactivar el último rol vivo que puede administrar permisos: sería
 *    tirar la llave adentro de la casa por la puerta de al lado.
 *
 * La bitácora de auditoría la escribe el observador de `SecRole`
 * ({@see RegistraBitacora}):
 * quién, cuándo y qué cambió, sin llamada manual.
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
     * @throws RolProtegido si el rol no es del panel, es el del sistema y se
     *                      renombra o desactiva, el nombre ya existe, o desactivar
     *                      dejaría al actor afuera o al sistema sin quién
     *                      administre permisos.
     */
    public function ejecutar(
        SecUser $actor,
        ?SecRole $rol,
        string $nombre,
        string $descripcion,
        bool $activo,
        ?int $idRolActivo,
    ): SecRole {
        $nombre = trim($nombre);
        $codigo = $rol === null ? self::PERMISO_CREAR : self::PERMISO_EDITAR;

        if (! $this->actorTienePermiso($actor, $idRolActivo, $codigo)) {
            throw PermisoDenegado::porFaltaDePermiso($codigo);
        }

        if ($rol !== null) {
            $this->verificarEdicion($rol, $nombre, $activo, $idRolActivo);
        }

        $this->verificarNombreLibre($nombre, $rol);

        if ($rol === null) {
            $rol = new SecRole([
                'name' => $nombre,
                'description' => $descripcion,
                'ambito' => 'panel',
                'state' => $activo,
            ]);
            $rol->created_by = $actor->id;
            $rol->updated_by = $actor->id;
            $rol->save();

            return $rol->refresh();
        }

        $rol->name = $nombre;
        $rol->description = $descripcion;
        $rol->state = $activo;
        $rol->updated_by = $actor->id;
        $rol->save();

        return $rol->refresh();
    }

    /**
     * @throws RolProtegido
     */
    private function verificarEdicion(SecRole $rol, string $nombre, bool $activo, ?int $idRolActivo): void
    {
        if (! $rol->esDelPanel()) {
            throw RolProtegido::porRolQueNoEsDelPanel((string) $rol->name);
        }

        if ($rol->esDelSistema() && ($nombre !== $rol->name || ! $activo)) {
            throw RolProtegido::porRolDelSistema((string) $rol->name);
        }

        if ($rol->state && ! $activo) {
            $this->verificarQueSePuedeDesactivar($rol, $idRolActivo);
        }
    }

    /**
     * El nombre no lo tiene otro rol, vivo o dado de baja, sin distinguir mayúsculas.
     *
     * @throws RolProtegido
     */
    private function verificarNombreLibre(string $nombre, ?SecRole $rol): void
    {
        $existe = SecRole::withTrashed()
            ->whereRaw('lower(name) = ?', [mb_strtolower($nombre)])
            ->when($rol !== null, fn ($consulta) => $consulta->where('id', '!=', $rol?->id))
            ->exists();

        if ($existe) {
            throw RolProtegido::porNombreDuplicado($nombre);
        }
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
