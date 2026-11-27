<?php

namespace App\Dominios\Seguridad\Dominio;

/**
 * Clases de permiso del panel (ADR 0046, puntos 4 y 5; plan de cuentas §7).
 *
 * El panel parte de dos roles sembrados. El `administrador` (id 1, el rol del
 * sistema) ve y hace todo; el `operador` y cualquier rol que el administrador
 * cree tienen los permisos que él marca en la matriz de su rol (una matriz por
 * rol, no por persona). Tres clases de permiso:
 *
 * - **Reservados** ({@see self::SOLO_ADMINISTRADOR}): usuarios, roles,
 *   configuración, edición de la organización, «ver como» y la carga de
 *   verificación por el administrador. Nunca los tiene el operador, aunque
 *   quien edita la matriz los tenga: `AsignarPermisosRol` rechaza otorgarlos y
 *   la matriz los muestra trabados.
 * - **Sensibles** ({@see self::SENSIBLES}): datos personales (selfie y
 *   documento, documentos del proveedor, chat y ubicación del servicio,
 *   bitácora). Se pueden asignar al operador, con aviso; no van en el preset.
 * - **Asignables**: el resto de los permisos del panel.
 *
 * Una sola lista para las piezas que la necesitan: el seeder (a quién se
 * siembran), `AsignarPermisosRol` (a quién se pueden otorgar desde la matriz)
 * y la pantalla de la matriz (qué interruptores se pintan trabados o con aviso).
 */
final class PermisosReservados
{
    public const ROL_ADMINISTRADOR = 'administrador';

    public const ROL_OPERADOR = 'operador';

    /** El rol del sistema: el administrador es el id 1 y no se elimina, renombra ni desactiva. */
    public const ID_ROL_ADMINISTRADOR = 1;

    /** @var list<string> Los dos roles que siembra el catálogo (ADR 0046, punto 4). */
    public const ROLES_DEL_PANEL = [self::ROL_ADMINISTRADOR, self::ROL_OPERADOR];

    /** @var list<string> */
    public const SOLO_ADMINISTRADOR = [
        'seguridad.usuario.ver',
        'seguridad.usuario.crear',
        'seguridad.usuario.editar',
        'seguridad.usuario.bloquear',
        'seguridad.usuario.eliminar',
        'seguridad.usuario.asignar_rol_administrador',
        'seguridad.rol.ver',
        'seguridad.rol.crear',
        'seguridad.rol.editar',
        'seguridad.rol.eliminar',
        'seguridad.rol.asignar_permiso',
        'seguridad.configuracion.ver',
        'seguridad.configuracion.editar',
        'seguridad.organizacion.editar',
        'seguridad.verificacion.cargar',
    ];

    /** @var list<string> */
    public const SENSIBLES = [
        'seguridad.verificacion.ver',
        'proveedores.documento.ver',
        'proveedores.documento.descargar',
        'proveedores.documento.eliminar',
        'solicitudes.solicitud.ver_seguimiento',
        'seguridad.bitacora.ver',
    ];

    /**
     * Lo que recibe el rol `operador` al sembrarse: solo lectura de lo no
     * sensible. No hay escritura, reservados ni sensibles (una prueba lo asegura).
     *
     * @var list<string>
     */
    public const PRESET_OPERADOR = [
        'seguridad.dashboard.ver',
        'solicitudes.solicitud.ver',
        'solicitudes.cliente.ver',
        'proveedores.proveedor.ver',
        'proveedores.piloto.ver',
        'flota.dron.ver',
        'calificaciones.calificacion.ver',
        'disputas.disputa.ver',
    ];

    public static function esReservado(string $codigoPermiso): bool
    {
        return in_array($codigoPermiso, self::SOLO_ADMINISTRADOR, true);
    }

    public static function esSensible(string $codigoPermiso): bool
    {
        return in_array($codigoPermiso, self::SENSIBLES, true);
    }

    /** Si un rol, por su nombre, puede tener los permisos reservados. */
    public static function admiteElRol(string $nombreRol): bool
    {
        return $nombreRol === self::ROL_ADMINISTRADOR;
    }

    /**
     * Si es el rol del sistema (el administrador): el id 1 o el nombre
     * `administrador`. Es el único rol que no se elimina, no se renombra ni se
     * desactiva, y el único cuya matriz no se edita.
     */
    public static function esRolDelSistema(int $idRol, string $nombreRol): bool
    {
        return $idRol === self::ID_ROL_ADMINISTRADOR || $nombreRol === self::ROL_ADMINISTRADOR;
    }
}
