<?php

namespace Database\Seeders;

use Database\Seeders\Catalogo\CatalogoSeeder;
use Database\Seeders\Demo\DemostracionSeeder;
use Illuminate\Database\Seeder;

/**
 * Dos familias de seeders, separadas desde el día uno (insumos §7):
 *
 * - Catálogo: datos que el sistema necesita para operar. Corren en TODOS los
 *   entornos, producción incluida.
 * - Demo: datos de ejemplo para ejecutar el flujo transaccional. Corren SOLO
 *   en local y staging — nunca en producción. Acá entra la demostración
 *   COMPLETA ({@see DemostracionSeeder}), no la familia mínima que usan los
 *   tests: `db:seed` a mano existe para poder mostrar el sistema, y una base
 *   con una sola orden y ninguna sesión no muestra nada.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Familia catálogo — todos los entornos (insumos §7.1).
        $this->call(CatalogoSeeder::class);

        // Familia demo — solo local y staging, nunca producción (insumos §7.2).
        if (app()->environment(['local', 'staging'])) {
            $this->call(DemostracionSeeder::class);
        }
    }
}
