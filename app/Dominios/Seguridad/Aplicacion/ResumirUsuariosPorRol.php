<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;

/**
 * Caso de uso de lectura para el tablero del administrador de plataforma
 * (tarea 139): cuántas cuentas internas activas hay en cada rol del catálogo.
 *
 * «Activa» es la misma que entiende el listado de usuarios: `state` verdadero
 * y sin baja lógica. Una cuenta con dos roles cuenta en cada uno de los dos —
 * por eso la suma de los roles puede superar a `total`, que cuenta cuentas
 * distintas. Los roles desactivados del catálogo no figuran: nadie puede
 * operar bajo ellos.
 *
 * Las cuentas del portal no tienen rol (entran por su contrato): van aparte,
 * en `portal`.
 */
final class ResumirUsuariosPorRol
{
    /**
     * @return array{total: int, portal: int, roles: list<array{rol: SecRole, usuarios: int}>}
     */
    public function ejecutar(): array
    {
        // El JOIN no hereda el borrado lógico de `sec_user`: se filtra a mano
        // (el de `sec_user_role` sí lo aplica su modelo).
        $cuentasPorRol = SecUserRole::query()
            ->join('sec_user', 'sec_user.id', '=', 'sec_user_role.id_user')
            ->whereNull('sec_user.deleted_at')
            ->where('sec_user.state', true)
            ->where('sec_user.type', TipoUsuario::Interno->value)
            ->groupBy('sec_user_role.id_role')
            ->selectRaw('sec_user_role.id_role as rol_id, count(distinct sec_user_role.id_user) as usuarios')
            ->pluck('usuarios', 'rol_id');

        $roles = SecRole::query()->where('state', true)->orderBy('id')->get();

        return [
            'total' => $this->activas(TipoUsuario::Interno),
            'portal' => $this->activas(TipoUsuario::Cliente),
            'roles' => array_values($roles->map(fn (SecRole $rol): array => [
                'rol' => $rol,
                'usuarios' => (int) ($cuentasPorRol[$rol->id] ?? 0),
            ])->all()),
        ];
    }

    private function activas(TipoUsuario $tipo): int
    {
        return SecUser::query()->where('type', $tipo->value)->where('state', true)->count();
    }
}
