<?php

namespace App\Dominios\Notificaciones\Dominio;

/**
 * Roles a los que una regla puede dirigir un aviso. El valor es la clave de
 * `sec_role.name` (`SeguridadSeeder::ROLES`), que `Seguridad` resuelve a
 * cuentas por `LecturaUsuariosPorRol`. Es un catálogo local y corto —solo los
 * roles que alguna regla usa hoy— para que un error de tipeo en una regla sea
 * un error de compilación y no un aviso que no llega a nadie.
 *
 * Los avisos de panel van al `administrador` (ADR 0046, punto 4): es quien ve y
 * hace todo. El operador no los recibe por rol, porque su matriz decide qué ve y
 * un aviso sobre algo que no puede abrir sería ruido; que el operador reciba los
 * avisos de lo que su matriz le deja ver es trabajo de la fase del tablero (A15).
 *
 * «Por rol» quiere decir rol ASIGNADO, no rol activo (ADR 0025 punto 3): una
 * notificación no es un permiso, así que llega igual aunque la cuenta esté
 * operando ahora con otro de sus roles.
 */
enum RolDestinatario: string
{
    case Administrador = 'administrador';
}
