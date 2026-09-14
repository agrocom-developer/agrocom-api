<?php

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Tarea 101 (HU-86): la migración 2026_09_14_100015 backfillea
 * `horas_inicial`/`horas_actual` desde `horas_uso` antes de dropear la
 * columna vieja. `RefreshDatabase` corre TODA la cascada de migraciones
 * antes de cada test, así que `horas_uso` ya no existe cuando el test
 * arranca — no hay forma de inspeccionar el estado intermedio dejando que
 * el framework migre solo.
 *
 * Patrón nuevo en este repo: se reconstruye a mano el escenario
 * "pre-migración" (dropear `horas_inicial`/`horas_actual`, recrear
 * `horas_uso` e insertar una fila con valor por SQL crudo, ya que
 * `Generador::$fillable` ya no la conoce) y se instancia la migración
 * directo (`require database_path(...)` devuelve el objeto anónimo con
 * `up()`/`down()`) para correr su `up()` real sobre ese esquema.
 */

uses(RefreshDatabase::class);

function montarEscenarioPreMigracionGenerador(): void
{
    Schema::table('man_generadores', function ($table) {
        $table->dropColumn(['horas_inicial', 'horas_actual']);
    });

    Schema::table('man_generadores', function ($table) {
        $table->decimal('horas_uso', 8, 2)->nullable();
    });
}

it('backfillea horas_inicial y horas_actual desde horas_uso al migrar', function () {
    montarEscenarioPreMigracionGenerador();

    DB::table('man_generadores')->insert([
        'identificador' => 'GEN-BACKFILL',
        'estado' => 'activo',
        'horas_uso' => '87.50',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migracion = require database_path('migrations/2026_09_14_100015_reemplaza_horas_uso_por_inicial_y_actual_en_man_generadores_table.php');
    $migracion->up();

    $generador = Generador::query()->where('identificador', 'GEN-BACKFILL')->sole();

    expect((float) $generador->horas_inicial)->toBe(87.50)
        ->and((float) $generador->horas_actual)->toBe(87.50);
});

it('no inventa horas_inicial ni horas_actual cuando horas_uso era nula', function () {
    montarEscenarioPreMigracionGenerador();

    DB::table('man_generadores')->insert([
        'identificador' => 'GEN-SIN-HORAS',
        'estado' => 'activo',
        'horas_uso' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migracion = require database_path('migrations/2026_09_14_100015_reemplaza_horas_uso_por_inicial_y_actual_en_man_generadores_table.php');
    $migracion->up();

    $generador = Generador::query()->where('identificador', 'GEN-SIN-HORAS')->sole();

    expect($generador->horas_inicial)->toBeNull()
        ->and($generador->horas_actual)->toBeNull();
});
