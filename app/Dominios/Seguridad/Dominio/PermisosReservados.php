<?php

namespace App\Dominios\Seguridad\Dominio;

/**
 * Clases de permiso del panel (ADR 0046, puntos 4 y 5; plan de cuentas §7).
 *
 * El panel tiene dos roles. El `administrador` ve y hace todo; el `operador`
 * tiene los permisos que el administrador marca en la matriz de su rol (una
 * matriz por rol, no por persona). Tres clases de permiso:
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

    /** @var list<string> Los dos roles del panel (ADR 0046, punto 4). */
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

    /** Si el nombre es el de uno de los dos roles del panel (catálogo fijo, ADR 0046 punto 4). */
    public static function esRolDelPanel(string $nombreRol): bool
    {
        return in_array($nombreRol, self::ROLES_DEL_PANEL, true);
    }
}
