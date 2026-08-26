<?php

namespace Database\Seeders;

use Database\Seeders\Catalogo\CatalogoSeeder;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Database\Seeder;

/**
 * Dos familias de seeders, separadas desde el día uno (insumos §7):
 *
 * - Catálogo: datos que el sistema necesita para operar. Corren en TODOS los
 *   entornos, producción incluida.
 * - Demo: datos de ejemplo para ejecutar el flujo transaccional. Corren SOLO
 *   en local y staging — nunca en producción.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Familia catálogo — todos los entornos (insumos §7.1).
        $this->call(CatalogoSeeder::class);

        // Familia demo — solo local y staging, nunca producción (insumos §7.2).
        if (app()->environment(['local', 'staging'])) {
            $this->call(DemoSeeder::class);
        }
    }
}
