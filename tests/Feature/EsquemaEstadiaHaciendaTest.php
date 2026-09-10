<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Esquema de `ope_estadias_hacienda` (espec §2.1, HU-51, tarea 74) — mismo
 * patrón que `EsquemaOperacionesTest`: soft delete + auditoría por defecto
 * (invariantes 8 y 9 de CLAUDE.md), el `UNIQUE` parcial por `uuid_cliente`
 * como mecanismo real de idempotencia (invariante 1), y el `UNIQUE` parcial
 * por `equipo_trabajo_id` que impide dos estadías abiertas a la vez —
 * verificados insertando directo con `DB::table()` y esperando que la base
 * los rechace, nunca un `SELECT` previo.
 */

uses(RefreshDatabase::class);

/** @return array{equipo_trabajo_id: int, campo_id: int} */
function crearEquipoYCampoParaEstadia(): array
{
    $base = PerBase::query()->create(['nombre' => 'Base de prueba '.uniqid()]);

    $equipo = EquipoTrabajo::query()->create([
        'codigo' => 'EQ-'.uniqid(),
        'base_id' => $base->id,
        'estado' => 'activo',
        'desde' => '2026-01-01',
    ]);

    $cliente = Cliente::query()->create(['razon_social' => 'Cliente de prueba '.uniqid(), 'tipo_persona' => 'juridica']);

    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo de prueba '.uniqid()]);

    return ['equipo_trabajo_id' => $equipo->id, 'campo_id' => $campo->id];
}

it('crea la tabla con soft delete y columnas de auditoría', function () {
    expect(Schema::hasTable('ope_estadias_hacienda'))->toBeTrue()
        ->and(Schema::hasColumns('ope_estadias_hacienda', [
            'uuid_cliente',
            'equipo_trabajo_id',
            'campo_id',
            'entrada',
            'salida',
            'vehiculo_id',
            'observacion',
            'cierre_uuid_cliente',
            'deleted_at',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('ope_estadias_hacienda', 'estado'))->toBeFalse();
});

it('rechaza una estadía con uuid_cliente repetido por el índice parcial', function () {
    $datos = crearEquipoYCampoParaEstadia();

    DB::table('ope_estadias_hacienda')->insert([
        'uuid_cliente' => 'uuid-estadia-duplicada',
        'equipo_trabajo_id' => $datos['equipo_trabajo_id'],
        'campo_id' => $datos['campo_id'],
        'entrada' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ope_estadias_hacienda')->insert([
        'uuid_cliente' => 'uuid-estadia-duplicada',
        'equipo_trabajo_id' => $datos['equipo_trabajo_id'],
        'campo_id' => $datos['campo_id'],
        'entrada' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('rechaza una segunda estadía abierta para el mismo equipo por el índice parcial', function () {
    $datos = crearEquipoYCampoParaEstadia();

    DB::table('ope_estadias_hacienda')->insert([
        'uuid_cliente' => 'uuid-estadia-1',
        'equipo_trabajo_id' => $datos['equipo_trabajo_id'],
        'campo_id' => $datos['campo_id'],
        'entrada' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Segunda estadía del MISMO equipo, sin cerrar la primera (salida NULL).
    DB::table('ope_estadias_hacienda')->insert([
        'uuid_cliente' => 'uuid-estadia-2',
        'equipo_trabajo_id' => $datos['equipo_trabajo_id'],
        'campo_id' => $datos['campo_id'],
        'entrada' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('permite una segunda estadía para el mismo equipo si la primera ya está cerrada', function () {
    $datos = crearEquipoYCampoParaEstadia();

    DB::table('ope_estadias_hacienda')->insert([
        'uuid_cliente' => 'uuid-estadia-cerrada',
        'equipo_trabajo_id' => $datos['equipo_trabajo_id'],
        'campo_id' => $datos['campo_id'],
        'entrada' => '2026-09-01 08:00:00',
        'salida' => '2026-09-02 08:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ope_estadias_hacienda')->insert([
        'uuid_cliente' => 'uuid-estadia-nueva',
        'equipo_trabajo_id' => $datos['equipo_trabajo_id'],
        'campo_id' => $datos['campo_id'],
        'entrada' => '2026-09-03 08:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('ope_estadias_hacienda')->where('equipo_trabajo_id', $datos['equipo_trabajo_id'])->count())->toBe(2);
});

it('permite una segunda estadía para el mismo equipo si la primera fue borrada lógicamente', function () {
    $datos = crearEquipoYCampoParaEstadia();

    DB::table('ope_estadias_hacienda')->insert([
        'uuid_cliente' => 'uuid-estadia-anulada',
        'equipo_trabajo_id' => $datos['equipo_trabajo_id'],
        'campo_id' => $datos['campo_id'],
        'entrada' => now(),
        'deleted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ope_estadias_hacienda')->insert([
        'uuid_cliente' => 'uuid-estadia-reintento',
        'equipo_trabajo_id' => $datos['equipo_trabajo_id'],
        'campo_id' => $datos['campo_id'],
        'entrada' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('ope_estadias_hacienda')->where('equipo_trabajo_id', $datos['equipo_trabajo_id'])->count())->toBe(2);
});

it('rechaza salida anterior o igual a la entrada por el CHECK (solo pgsql)', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('CHECK solo existe en pgsql; SQLite no soporta ADD CONSTRAINT (ver docblock de la migración).');
    }

    $datos = crearEquipoYCampoParaEstadia();

    DB::table('ope_estadias_hacienda')->insert([
        'uuid_cliente' => 'uuid-estadia-chk',
        'equipo_trabajo_id' => $datos['equipo_trabajo_id'],
        'campo_id' => $datos['campo_id'],
        'entrada' => '2026-09-01 08:00:00',
        'salida' => '2026-09-01 08:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);
