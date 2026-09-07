<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;

/**
 * La demostración completa: el set con el que se puede sentar a alguien
 * frente al panel y recorrerlo entero sin encontrar una pantalla vacía.
 *
 * Se apoya en {@see DemoSeeder} —la familia mínima, que además corre en los
 * tests— y le agrega volumen y variedad de estados. La separación es
 * deliberada: los tests afirman sobre el tamaño de lo que siembra
 * `DemoSeeder` («una orden vigente», «un lote»), así que el material de
 * demostración no puede vivir ahí adentro. Solo lo invoca `DatabaseSeeder`,
 * en local y staging.
 *
 * El orden es una cadena de dependencias, no una preferencia:
 *
 * 1. `CarteraClientesDemoSeeder` — los otros tres clientes (San Marcos, El
 *    Carmen, Santa Rosa), que son los campos del relato de las capturas de RC.
 * 2. `FlotaDemoSeeder` — drones, baterías, vehículos, repuestos y stock, más
 *    su mantenimiento. Las sesiones necesitan un dron al que colgarse.
 * 3. `OperacionDemoSeeder` — las 21 capturas reales del control remoto como
 *    evidencias, y sobre ellas los trabajos, sesiones, validaciones (que
 *    generan los devengos), actas y reportes técnicos.
 * 4. `FinanzasDemoSeeder` — lo que se deriva de todo eso: gastos, rendiciones,
 *    combustible, anticipos, la planilla del período y las facturas.
 *
 * Todos idempotentes: correr `db:seed` dos veces no duplica nada.
 */
class DemostracionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoSeeder::class);
        $this->call(CarteraClientesDemoSeeder::class);
        $this->call(FlotaDemoSeeder::class);
        $this->call(OperacionDemoSeeder::class);
        $this->call(FinanzasDemoSeeder::class);
    }
}
