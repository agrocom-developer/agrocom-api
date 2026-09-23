<?php

namespace App\Dominios\Seguridad\Dominio;

/**
 * Permisos de plataforma: capacidades de soporte técnico que solo puede tener
 * el rol `admin_plataforma` (tarea 140). No son un permiso más del negocio —
 * el dueño, que recibe el resto del catálogo, tampoco los tiene.
 *
 * Una sola lista para las tres piezas que la necesitan: el seeder (a quién se
 * siembran), `AsignarPermisosRol` (a quién se pueden otorgar desde la matriz)
 * y la pantalla de la matriz (qué interruptores se pintan trabados).
 */
final class PermisosReservados
{
    public const ROL_ADMIN_PLATAFORMA = 'admin_plataforma';

    /** @var list<string> */
    public const SOLO_ADMIN_PLATAFORMA = [
        'seguridad.usuario.ver_como',
    ];

    public static function esReservado(string $codigoPermiso): bool
    {
        return in_array($codigoPermiso, self::SOLO_ADMIN_PLATAFORMA, true);
    }

    /** Si un rol, por su nombre, puede tener los permisos reservados. */
    public static function admiteElRol(string $nombreRol): bool
    {
        return $nombreRol === self::ROL_ADMIN_PLATAFORMA;
    }
}
