<?php

namespace Database\Seeders\Demo;

use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Http\Demo\DatosDemoPanel;
use Illuminate\Database\Seeder;

/**
 * Demo del panel (quinta vuelta — maquetas aprobadas 4a/5a/5b/5c): el
 * usuario multirol de las maquetas, `camila.rojas` / `password`, con tres
 * roles vivos (`dueno`, `piloto`, `encargado_operaciones`) — lo mínimo para
 * demostrar la pantalla de selección de rol (maqueta 5c: tarjetas, badge
 * "ÚLTIMO USADO", "Entrar siempre con este rol") y el menú filtrado por rol
 * activo. Los datos del dashboard NO viven acá: son presentación mock
 * ({@see DatosDemoPanel})
 * porque las tablas de dominio que los sustentarían (sesiones, pausas,
 * stock) todavía no existen.
 *
 * Escribe directo por los modelos Eloquent de Seguridad (mismo criterio que
 * `SeguridadSeeder` con `SecRolePermission`): un seeder no pasa por
 * `AsignarRolesUsuario`, que exige un actor con permisos.
 *
 * Idempotente: `firstOrCreate` por username y por par (usuario, rol).
 */
class PanelDemoSeeder extends Seeder
{
    /** @var list<string> */
    private const ROLES_DEMO = ['dueno', 'piloto', 'encargado_operaciones'];

    public function run(): void
    {
        $usuaria = SecUser::query()->firstOrCreate(
            ['username' => 'camila.rojas'],
            ['name' => 'Camila Rojas', 'password' => 'password', 'type' => TipoUsuario::Interno],
        );

        foreach (self::ROLES_DEMO as $nombreRol) {
            $rol = SecRole::query()->where('name', $nombreRol)->first();

            if ($rol === null) {
                continue; // catálogo de roles no sembrado — nada que asignar
            }

            $yaAsignado = SecUserRole::query()
                ->where('id_user', $usuaria->id)
                ->where('id_role', $rol->id)
                ->exists();

            if ($yaAsignado) {
                continue;
            }

            (new SecUserRole([
                'id_user' => $usuaria->id,
                'id_role' => $rol->id,
            ]))->save();
        }
    }
}
