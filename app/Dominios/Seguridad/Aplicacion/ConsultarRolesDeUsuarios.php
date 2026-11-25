<?php

namespace App\Dominios\Seguridad\Aplicacion;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lecturas de roles por cuenta para el ABM de usuarios del panel (consultas
 * en una sola pasada para evitar N+1 en el listado). Devuelven el slug del
 * rol: ponerle nombre legible es presentación.
 */
final class ConsultarRolesDeUsuarios
{
    /**
     * Slugs de los roles asignados (no revocados) de cada usuario, ordenados
     * por nombre.
     *
     * @param  list<int>  $idsUsuario
     * @return array<int, list<string>>
     */
    public function slugsPorUsuario(array $idsUsuario): array
    {
        if ($idsUsuario === []) {
            return [];
        }

        return DB::table('sec_user_role as ur')
            ->join('sec_role as r', 'r.id', '=', 'ur.id_role')
            ->whereIn('ur.id_user', $idsUsuario)
            ->whereNull('ur.deleted_at')
            ->orderBy('r.name')
            ->get(['ur.id_user', 'r.name'])
            ->groupBy(fn (object $fila): int => (int) $fila->id_user)
            ->map(fn (Collection $filas) => $filas->map(fn (object $fila) => (string) $fila->name)->all())
            ->all();
    }

    /**
     * Roles VIVOS y habilitados de cada cuenta: asignación no revocada y rol
     * habilitado en el catálogo (mismos criterios que
     * `SecUser::idsDeRolesActivos()`), en una sola consulta.
     *
     * @param  list<int>  $idsUsuario
     * @return array<int, array<int, string>> `id de usuario => [id de rol => slug]`
     */
    public function slugsVivosPorUsuario(array $idsUsuario): array
    {
        if ($idsUsuario === []) {
            return [];
        }

        return DB::table('sec_user_role as ur')
            ->join('sec_role as r', 'r.id', '=', 'ur.id_role')
            ->whereIn('ur.id_user', $idsUsuario)
            ->whereNull('ur.deleted_at')
            ->whereNull('r.deleted_at')
            ->where('r.state', true)
            ->orderBy('r.name')
            ->get(['ur.id_user', 'r.id', 'r.name'])
            ->groupBy(fn (object $fila): int => (int) $fila->id_user)
            ->map(fn (Collection $filas) => $filas
                ->mapWithKeys(fn (object $fila): array => [(int) $fila->id => (string) $fila->name])
                ->all())
            ->all();
    }

    /**
     * @return list<int> IDs de rol con el permiso `$codigoPermiso` otorgado y
     *                   vivo en el catálogo (`sec_role_permission`/`sec_permission`).
     */
    public function idsDeRolesConPermiso(string $codigoPermiso): array
    {
        return DB::table('sec_role_permission')
            ->join('sec_permission', 'sec_permission.id', '=', 'sec_role_permission.id_permission')
            ->whereNull('sec_role_permission.deleted_at')
            ->whereNull('sec_permission.deleted_at')
            ->where('sec_permission.code', $codigoPermiso)
            ->where('sec_permission.state', true)
            ->pluck('sec_role_permission.id_role')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
