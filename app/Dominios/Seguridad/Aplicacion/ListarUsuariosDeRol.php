<?php

namespace App\Dominios\Seguridad\Aplicacion;

use Illuminate\Support\Facades\DB;

/**
 * Las cuentas vivas que tienen un rol, para la ficha del rol («Ver rol»): su
 * nombre, su usuario y si el acceso está activo o bloqueado, por nombre. Es la
 * misma noción de «cuenta viva con el rol» que usa {@see EliminarRol} para
 * decidir si el rol se puede eliminar, así que el número que muestra la ficha es
 * exactamente el que bloquea (o permite) la baja.
 */
final class ListarUsuariosDeRol
{
    /**
     * @return list<array{id: int, nombre: string, usuario: string, activo: bool}>
     */
    public function ejecutar(int $idRol): array
    {
        return DB::table('sec_user_role as ur')
            ->join('sec_user as u', 'u.id', '=', 'ur.id_user')
            ->where('ur.id_role', $idRol)
            ->whereNull('ur.deleted_at')
            ->whereNull('u.deleted_at')
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.username', 'u.state'])
            ->map(static fn (object $fila): array => [
                'id' => (int) $fila->id,
                'nombre' => (string) $fila->name,
                'usuario' => (string) $fila->username,
                'activo' => (bool) $fila->state,
            ])
            ->all();
    }
}
