<?php

namespace Database\Seeders;

use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Database\Seeder;

/**
 * Tarea 100: se retira la familia `Demo/` (datos de ejemplo para ejercitar
 * el flujo transaccional a mano) — el usuario ya no confía en que la suite
 * automática refleje que el sistema hace lo que se pide, y prefiere levantar
 * el sistema con el mínimo dato estructural y probar todo en vivo desde el
 * panel. Queda una sola familia de catálogo, más la cuenta de arranque:
 *
 * - Catálogo ({@see CatalogoSeeder}): datos que el sistema necesita para
 *   operar (menú, roles, permisos). Corre en TODOS los entornos, producción
 *   incluida.
 * - Cuenta de arranque ({@see AdminPlataformaSeeder}): la única cuenta con
 *   la que arranca una instalación nueva de `local`/`staging` — el resto de
 *   los datos los carga el usuario a mano desde el panel.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogoSeeder::class);

        if (app()->environment(['local', 'staging'])) {
            $this->call(AdminPlataformaSeeder::class);
        }
    }
}
