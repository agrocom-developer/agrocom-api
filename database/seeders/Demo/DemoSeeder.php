<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;

/**
 * Familia demo MÍNIMA (insumos §7.2): el escenario más chico con el que el
 * flujo transaccional puede arrancar — la cuadrilla con sus cuentas, y un
 * cliente con contrato, campo, lotes y una orden vigente.
 *
 * Es el que invocan los tests (`$this->seed(DemoSeeder::class)`), y por eso
 * se mantiene deliberadamente chico: decenas de tests afirman sobre el
 * TAMAÑO de lo sembrado —«una orden vigente», «un lote», «cinco filas de
 * catálogo en el primer pull»— y engordarlo los rompe a todos sin que
 * ninguno esté equivocado.
 *
 * Lo que engorda el set para poder MOSTRAR el sistema —tres clientes más, la
 * flota, ocho trabajos con sus sesiones y evidencias, y toda la cara
 * financiera— vive en {@see DemostracionSeeder}, que corre desde
 * `DatabaseSeeder` en local y staging pero que ningún test invoca salvo el
 * que verifica la demostración en sí.
 *
 * El orden importa: `PersonalDemoSeeder` va primero porque todo lo demás
 * necesita un autor (`created_by`) y las personas a las que colgar sesiones,
 * devengos y anticipos.
 *
 * Ambos son idempotentes: correr `db:seed` dos veces no duplica nada.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PersonalDemoSeeder::class);
        $this->call(NucleoComercialSeeder::class);
    }
}
