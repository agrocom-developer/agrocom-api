<?php

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use Illuminate\Database\Migrations\Migration;

/**
 * Tarea 77 (HU-54, pedido del dueño 7/9/2026): separa el ítem de menú
 * "Campos y lotes" en "Propiedades" y "Lotes", y renombra "Personas" a
 * "Personal".
 *
 * Migración de DATOS de catálogo, no de esquema: existe para que una
 * instalación ya desplegada reciba el árbol nuevo con un `php artisan
 * migrate` de rutina, sin depender de que alguien se acuerde de correr
 * `db:seed --class=SecMenuSeeder` a mano. `SecMenuSeeder` queda con la misma
 * transformación (fuente de verdad para instalaciones nuevas y para los
 * tests, que corren con `RefreshDatabase` + seed) — de ahí que en una base
 * sembrada desde cero esta migración no encuentre nada que tocar (todavía no
 * existe `sec_menu`) y el seeder haga el trabajo completo.
 *
 * Solo toca `sec_menu` (rename de dos labels + una fila nueva): no otorga ni
 * quita ninguna fila de `sec_role_permission`, así que no puede pisar un
 * permiso que un dueño haya quitado a mano a un rol desde el panel (tarea
 * 64) — esa asignación sigue siendo responsabilidad exclusiva de
 * `SeguridadSeeder`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercial = SecMenu::query()
            ->whereNull('padre_id')
            ->where('label', 'menu.comercial.label')
            ->first();

        if ($comercial !== null) {
            SecMenu::query()
                ->where('padre_id', $comercial->id)
                ->where('label', 'menu.comercial.items.campos')
                ->update(['label' => 'menu.comercial.items.propiedades']);

            $idPermisoLote = SecPermission::query()->where('code', 'comercial.lote.ver')->value('id');

            SecMenu::query()->firstOrCreate(
                ['label' => 'menu.comercial.items.lotes', 'padre_id' => $comercial->id],
                [
                    'icono' => 'grid_view',
                    'ruta' => null,
                    'orden' => 4,
                    'permission_id' => $idPermisoLote,
                ],
            );
        }

        $recursos = SecMenu::query()
            ->whereNull('padre_id')
            ->where('label', 'menu.recursos.label')
            ->first();

        if ($recursos !== null) {
            SecMenu::query()
                ->where('padre_id', $recursos->id)
                ->where('label', 'menu.recursos.items.personas')
                ->update(['label' => 'menu.recursos.items.personal']);
        }
    }

    public function down(): void
    {
        $comercial = SecMenu::query()
            ->whereNull('padre_id')
            ->where('label', 'menu.comercial.label')
            ->first();

        if ($comercial !== null) {
            SecMenu::query()
                ->where('padre_id', $comercial->id)
                ->where('label', 'menu.comercial.items.propiedades')
                ->update(['label' => 'menu.comercial.items.campos']);

            SecMenu::query()
                ->where('padre_id', $comercial->id)
                ->where('label', 'menu.comercial.items.lotes')
                ->delete();
        }

        $recursos = SecMenu::query()
            ->whereNull('padre_id')
            ->where('label', 'menu.recursos.label')
            ->first();

        if ($recursos !== null) {
            SecMenu::query()
                ->where('padre_id', $recursos->id)
                ->where('label', 'menu.recursos.items.personal')
                ->update(['label' => 'menu.recursos.items.personas']);
        }
    }
};
