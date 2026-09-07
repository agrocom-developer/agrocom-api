<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Listado de roles con lo que la pantalla necesita para que cada fila diga
 * algo: cuántas PANTALLAS ve el rol, cuántas ACCIONES puede ejercer, y
 * cuántas cuentas vivas lo tienen asignado.
 *
 * La partición pantallas/acciones es la misma que explica
 * {@see CatalogoDePermisos}: "34 permisos" no le dice nada a nadie, "ve 6
 * pantallas y puede hacer 3 cosas" sí. Se resuelve con dos consultas
 * agregadas y no recorriendo el árbol por rol: con 5 roles daría igual, pero
 * el árbol se arma con tres joins y esta pantalla no tiene por qué pagarlos
 * cinco veces.
 *
 * `usuarios` cuenta asignaciones vivas a cuentas VIVAS — el mismo criterio
 * que usa {@see EliminarRol} para decidir si el rol se puede dar de baja, así
 * que el número que muestra la fila es exactamente el que va a bloquear (o
 * permitir) el botón de baja.
 */
final class ListarRolesConPermisos
{
    /**
     * @return Collection<int, array{
     *     rol: SecRole, pantallas: int<0, max>, acciones: int, usuarios: int
     * }>
     */
    public function ejecutar(): Collection
    {
        $roles = SecRole::query()->orderBy('name')->get();

        $idsPantalla = DB::table('sec_menu')
            ->whereNull('deleted_at')
            ->whereNotNull('permission_id')
            ->pluck('permission_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->unique()
            ->all();

        $porRol = DB::table('sec_role_permission as rp')
            ->join('sec_permission as p', 'p.id', '=', 'rp.id_permission')
            ->whereNull('rp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.state', true)
            ->get(['rp.id_role', 'rp.id_permission'])
            ->groupBy(static fn (object $fila): int => (int) $fila->id_role);

        // `count(*) as total` con alias explícito, no `pluck(DB::raw('count(*)'))`:
        // `pluck` lee la propiedad por nombre y la columna volvería llamándose
        // literalmente `count(*)`, que no es un identificador válido.
        $usuariosPorRol = DB::table('sec_user_role as ur')
            ->join('sec_user as u', 'u.id', '=', 'ur.id_user')
            ->whereNull('ur.deleted_at')
            ->whereNull('u.deleted_at')
            ->groupBy('ur.id_role')
            ->selectRaw('ur.id_role, count(*) as total')
            ->pluck('total', 'id_role');

        return $roles->map(function (SecRole $rol) use ($porRol, $usuariosPorRol, $idsPantalla): array {
            $suyos = $porRol->get($rol->id, collect())
                ->map(static fn (object $fila): int => (int) $fila->id_permission);

            $pantallas = $suyos->filter(static fn (int $id): bool => in_array($id, $idsPantalla, true))->count();

            return [
                'rol' => $rol,
                'pantallas' => $pantallas,
                'acciones' => $suyos->count() - $pantallas,
                'usuarios' => (int) ($usuariosPorRol[$rol->id] ?? 0),
            ];
        });
    }

    /**
     * Totales del catálogo, para el denominador de cada fila ("6 / 31"). Sin
     * ellos el número suelto no dice si el rol está acotado o casi completo.
     *
     * @return array{pantallas: int, acciones: int}
     */
    public function totales(): array
    {
        $pantallas = DB::table('sec_menu as m')
            ->join('sec_permission as p', 'p.id', '=', 'm.permission_id')
            ->whereNull('m.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.state', true)
            ->distinct()
            ->count('p.id');

        $total = DB::table('sec_permission')
            ->whereNull('deleted_at')
            ->where('state', true)
            ->count();

        return ['pantallas' => $pantallas, 'acciones' => $total - $pantallas];
    }
}
