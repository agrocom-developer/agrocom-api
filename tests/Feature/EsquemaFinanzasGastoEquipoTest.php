<?php

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Tarea 73 (HU-50) — el gasto y el combustible se imputan al equipo de
 * trabajo: `fin_gastos.equipo_trabajo_id` (nullable, ALTER TABLE) y la
 * reescritura de `fin_combustibles` (`destino` → `equipo_trabajo_id` +
 * `campania_id` + `recurso_tipo` + `recurso_id`).
 */

uses(RefreshDatabase::class);

it('agrega equipo_trabajo_id a fin_gastos por ALTER TABLE', function () {
    expect(Schema::hasColumn('fin_gastos', 'equipo_trabajo_id'))->toBeTrue();
});

it('fin_combustibles reemplaza destino por equipo_trabajo_id, campania_id, recurso_tipo y recurso_id', function () {
    expect(Schema::hasColumns('fin_combustibles', [
        'equipo_trabajo_id',
        'campania_id',
        'recurso_tipo',
        'recurso_id',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('fin_combustibles', 'destino'))->toBeFalse();
});

it('migra las filas preexistentes de fin_combustibles: conservan destino como recurso_tipo y ninguna queda sin equipo_trabajo_id', function () {
    $base = PerBase::query()->create(['nombre' => 'Base legado '.uniqid()]);

    $equipo = EquipoTrabajo::query()->create([
        'codigo' => 'EQ-LEGADO-'.uniqid(),
        'base_id' => $base->id,
        'estado' => 'activo',
        'desde' => '2026-01-01',
    ]);

    $generador = Generador::query()->create([
        'identificador' => 'GEN-LEGADO-'.uniqid(),
        'base_id' => $base->id,
        'estado' => 'activo',
    ]);

    $vehiculo = Vehiculo::query()->create([
        'identificador' => 'VEH-LEGADO-'.uniqid(),
        'base_id' => $base->id,
        'estado' => 'activo',
    ]);

    $rutaMigracion = 'database/migrations/2026_09_09_100002_agrega_equipo_recurso_campania_a_fin_combustibles_table.php';

    $this->artisan('migrate:rollback', ['--path' => $rutaMigracion, '--realpath' => false])->run();

    expect(Schema::hasColumn('fin_combustibles', 'equipo_trabajo_id'))->toBeFalse()
        ->and(Schema::hasColumn('fin_combustibles', 'destino'))->toBeTrue();

    $ahora = now();

    $filaGenerador = DB::table('fin_combustibles')->insertGetId([
        'fecha' => '2026-09-01',
        'base_id' => $base->id,
        'destino' => 'generador',
        'litros' => '50.00',
        'monto' => '350.00',
        'created_at' => $ahora,
        'updated_at' => $ahora,
    ]);

    $filaVehiculo = DB::table('fin_combustibles')->insertGetId([
        'fecha' => '2026-09-02',
        'base_id' => $base->id,
        'destino' => 'vehiculo',
        'litros' => '30.00',
        'monto' => '210.00',
        'created_at' => $ahora,
        'updated_at' => $ahora,
    ]);

    $this->artisan('migrate', ['--path' => $rutaMigracion, '--realpath' => false])->run();

    expect(DB::table('fin_combustibles')->whereNull('equipo_trabajo_id')->count())->toBe(0)
        ->and(DB::table('fin_combustibles')->whereNull('recurso_tipo')->count())->toBe(0)
        ->and(DB::table('fin_combustibles')->whereNull('recurso_id')->count())->toBe(0);

    $migrada = DB::table('fin_combustibles')->whereIn('id', [$filaGenerador, $filaVehiculo])->get()->keyBy('id');

    expect($migrada[$filaGenerador]->recurso_tipo)->toBe('generador')
        ->and($migrada[$filaGenerador]->equipo_trabajo_id)->toBe($equipo->id)
        ->and($migrada[$filaGenerador]->recurso_id)->toBe($generador->id)
        ->and($migrada[$filaVehiculo]->recurso_tipo)->toBe('vehiculo')
        ->and($migrada[$filaVehiculo]->equipo_trabajo_id)->toBe($equipo->id)
        ->and($migrada[$filaVehiculo]->recurso_id)->toBe($vehiculo->id);
});
