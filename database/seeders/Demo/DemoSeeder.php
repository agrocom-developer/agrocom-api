<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;

/**
 * Orquestador de la familia demo (insumos §7.2): datos de ejemplo para
 * ejecutar el flujo transaccional completo. Solo local y staging — el guard
 * de entorno vive en DatabaseSeeder; los tests lo invocan directamente.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(NucleoComercialSeeder::class);
        $this->call(PanelDemoSeeder::class);
    }
}
