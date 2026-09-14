<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/*
 * Esquema de `cpn_campanias.estacion` (HU-77, tarea 93): `ALTER` aditivo
 * sobre una tabla existente — ver
 * `database/migrations/2026_09_14_100006_add_estacion_a_cpn_campanias_table.php`.
 * El `CHECK`/`NOT NULL` reales solo se agregan en pgsql (SQLite, motor de
 * los tests, no soporta alterar columnas existentes sin `doctrine/dbal`) —
 * la obligatoriedad y el catálogo cerrado los cubre
 * `tests/Feature/Campania/GestionCampaniasPanelTest.php` a nivel de Request.
 */

uses(RefreshDatabase::class);

it('agrega la columna estacion a cpn_campanias', function () {
    expect(Schema::hasColumn('cpn_campanias', 'estacion'))->toBeTrue();
});
