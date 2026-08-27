<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use Illuminate\Database\Seeder;

/**
 * Catálogo de ítems de menú del panel (HU-02, ADR 0002 punto 5): corre en
 * todos los entornos, producción incluida — igual que `SeguridadSeeder`, del
 * que depende (necesita `sec_permission` ya sembrado). Sin autor explícito
 * (`created_by`/`updated_by` quedan NULL): es dato de catálogo de sistema,
 * mismo criterio que `SeguridadSeeder`.
 *
 * Solo se siembran los ítems para lo que YA existe en el sistema — nada de
 * pantallas todavía sin construir:
 * - "Inicio": sin permiso (`permission_id` null), visible para cualquier
 *   usuario autenticado del panel.
 * - "Usuarios": gateado por `seguridad.usuario.ver` (el permiso de listado,
 *   no los de crear/editar/bloquear/eliminar/asignar_rol_dueno — esos rigen
 *   acciones DENTRO de la pantalla, no la visibilidad del ítem de menú que
 *   lleva a ella).
 *
 * Ningún permiso de `sec_role`/`sec_menu` propios existe todavía en el
 * catálogo de `SeguridadSeeder` (6 permisos, todos `seguridad.usuario.*`),
 * así que no se inventa un ítem "Roles" ni "Menús" huérfano de permiso real.
 *
 * `ruta` guarda nombres de ruta por convención (`panel.dashboard`,
 * `panel.usuarios.index`) todavía no registrados en `routes/web.php` — el
 * mismo criterio de tolerancia que ya usa
 * `ResolverRolActivo::RUTA_SELECTOR` (documentar el contrato antes de que
 * `frontend` registre la ruta real, sin bloquear este seeder por eso).
 *
 * Idempotente vía `firstOrCreate` sobre `(label, padre_id)`, mismo criterio
 * que `SeguridadSeeder`: correrlo de nuevo no duplica filas ni pisa
 * `icono`/`ruta`/`orden`/`permission_id` si ya fueron editados a mano.
 */
class SecMenuSeeder extends Seeder
{
    public function run(): void
    {
        $this->item(
            label: 'seguridad.menu.inicio',
            icono: 'home',
            ruta: 'panel.dashboard',
            orden: 1,
            codigoPermiso: null,
        );

        $this->item(
            label: 'seguridad.menu.usuarios',
            icono: 'group',
            ruta: 'panel.usuarios.index',
            orden: 2,
            codigoPermiso: 'seguridad.usuario.ver',
        );
    }

    private function item(string $label, string $icono, ?string $ruta, int $orden, ?string $codigoPermiso): SecMenu
    {
        $permissionId = $codigoPermiso === null
            ? null
            : SecPermission::query()->where('code', $codigoPermiso)->value('id');

        return SecMenu::query()->firstOrCreate(
            ['label' => $label, 'padre_id' => null],
            [
                'icono' => $icono,
                'ruta' => $ruta,
                'orden' => $orden,
                'permission_id' => $permissionId,
            ],
        );
    }
}
