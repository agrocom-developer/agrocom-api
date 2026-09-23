<?php

namespace App\Dominios\Seguridad\Contratos;

/**
 * Frontera de lectura de Seguridad hacia `Notificaciones` (ADR 0003, regla 2;
 * tarea 141, ADR 0025): a qué cuentas hay que avisar cuando un hecho le
 * importa a un ROL, sin que el módulo que avisa importe `SecUser`.
 *
 * La pregunta es «quién TIENE este rol», no «quién lo está usando ahora». El
 * invariante 10 de CLAUDE.md dice que los permisos efectivos son los del rol
 * activo de la sesión; una notificación no es un permiso —enterarse de que
 * algo ocurrió no concede poder hacerlo—, así que se responde por rol
 * ASIGNADO (`sec_user_role`) aunque la cuenta esté operando con otro de sus
 * roles. Ver el punto 3 del ADR 0025.
 */
interface LecturaUsuariosPorRol
{
    /**
     * `$claveRol` es `sec_role.name` (`dueno`, `jefe_campo`, `encargado_operaciones`…).
     * Solo cuentas INTERNAS vivas y no bloqueadas (`sec_user.state`), con la
     * asignación viva y el rol activo en el catálogo (`sec_role.state`). Una
     * clave que no existe devuelve una lista vacía, no un error.
     *
     * @return list<int> ids de `sec_user`, sin repetir
     */
    public function idsConRol(string $claveRol): array;
}
