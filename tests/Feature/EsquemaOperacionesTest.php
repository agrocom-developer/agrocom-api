<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Esquema de `trabajo`/`sesion` (espec §4.3, TE-05, tarea 09) — mismo patrón
 * que `EsquemaNucleoComercialTest`: soft delete + auditoría por defecto
 * (invariantes 8 y 9 de CLAUDE.md) y el `UNIQUE` parcial por `uuid_cliente`
 * como mecanismo real de idempotencia (invariante 1), verificado insertando
 * un duplicado y esperando que la base lo rechace — nunca un `SELECT` previo.
 */

uses(RefreshDatabase::class);

dataset('tablas de operaciones con uuid_cliente', [
    'ope_trabajos',
    'ope_sesiones',
]);

it('crea la tabla con soft delete y columnas de auditoría', function (string $tabla) {
    expect(Schema::hasTable($tabla))->toBeTrue()
        ->and(Schema::hasColumns($tabla, [
            'uuid_cliente',
            'estado',
            'deleted_at',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
})->with('tablas de operaciones con uuid_cliente');

it('ope_trabajos tiene equipo_trabajo_id, nullable (HU-70, tarea 85)', function () {
    expect(Schema::hasColumn('ope_trabajos', 'equipo_trabajo_id'))->toBeTrue();

    $orden = crearOrdenVigenteParaTrabajo();

    $trabajoId = DB::table('ope_trabajos')->insertGetId([
        'uuid_cliente' => 'uuid-trabajo-sin-equipo',
        'orden_id' => $orden['orden_id'],
        'lote_id' => $orden['lote_id'],
        'nro_aplicacion' => 1,
        'estado' => 'abierto',
        'inicio' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('ope_trabajos')->where('id', $trabajoId)->value('equipo_trabajo_id'))->toBeNull();
});

/** @return array{orden_id: int, lote_id: int} */
function crearOrdenVigenteParaTrabajo(): array
{
    $clienteId = DB::table('com_clientes')->insertGetId([
        'razon_social' => 'Cliente de prueba',
        'tipo_persona' => 'juridica',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $propiedadId = DB::table('com_propiedades')->insertGetId([
        'cliente_id' => $clienteId,
        'nombre' => 'Propiedad de prueba',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $loteId = DB::table('com_lotes')->insertGetId([
        'propiedad_id' => $propiedadId,
        'codigo' => 'L-TEST',
        'hectareas' => '50.00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $contratoId = DB::table('com_contratos')->insertGetId([
        'cliente_id' => $clienteId,
        'hectareas_contratadas' => '50.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '500.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => 'vigente',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ordenId = DB::table('ope_ordenes_aplicacion')->insertGetId([
        'contrato_id' => $contratoId,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => 'vigente',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['orden_id' => $ordenId, 'lote_id' => $loteId];
}

it('rechaza un trabajo con uuid_cliente repetido por el índice parcial', function () {
    $orden = crearOrdenVigenteParaTrabajo();

    DB::table('ope_trabajos')->insert([
        'uuid_cliente' => 'uuid-trabajo-duplicado',
        'orden_id' => $orden['orden_id'],
        'lote_id' => $orden['lote_id'],
        'nro_aplicacion' => 1,
        'estado' => 'abierto',
        'inicio' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ope_trabajos')->insert([
        'uuid_cliente' => 'uuid-trabajo-duplicado',
        'orden_id' => $orden['orden_id'],
        'lote_id' => $orden['lote_id'],
        'nro_aplicacion' => 1,
        'estado' => 'abierto',
        'inicio' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('crea ope_orden_lotes con soft delete, columnas de auditoría y hectareas_solicitadas (HU-92, tarea 107)', function () {
    expect(Schema::hasTable('ope_orden_lotes'))->toBeTrue()
        ->and(Schema::hasColumns('ope_orden_lotes', [
            'orden_id',
            'lote_id',
            'hectareas_solicitadas',
            'deleted_at',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

it('rechaza un lote repetido dentro de la misma orden por el índice único parcial (HU-92, tarea 107)', function () {
    $orden = crearOrdenVigenteParaTrabajo();

    DB::table('ope_orden_lotes')->insert([
        'orden_id' => $orden['orden_id'],
        'lote_id' => $orden['lote_id'],
        'hectareas_solicitadas' => '10.00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ope_orden_lotes')->insert([
        'orden_id' => $orden['orden_id'],
        'lote_id' => $orden['lote_id'],
        'hectareas_solicitadas' => '5.00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('permite repetir orden+lote si la fila anterior está soft-deleted (HU-92, tarea 107)', function () {
    $orden = crearOrdenVigenteParaTrabajo();

    DB::table('ope_orden_lotes')->insert([
        'orden_id' => $orden['orden_id'],
        'lote_id' => $orden['lote_id'],
        'hectareas_solicitadas' => '10.00',
        'deleted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ope_orden_lotes')->insert([
        'orden_id' => $orden['orden_id'],
        'lote_id' => $orden['lote_id'],
        'hectareas_solicitadas' => '5.00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('ope_orden_lotes')->where('orden_id', $orden['orden_id'])->count())->toBe(2);
});

it('rechaza hectareas_solicitadas en cero o negativo por el CHECK (solo pgsql)', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('CHECK solo existe en pgsql; SQLite no soporta ADD CONSTRAINT (ver docblock de la migración).');
    }

    $orden = crearOrdenVigenteParaTrabajo();

    DB::table('ope_orden_lotes')->insert([
        'orden_id' => $orden['orden_id'],
        'lote_id' => $orden['lote_id'],
        'hectareas_solicitadas' => '0.00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('rechaza una sesión con uuid_cliente repetido por el índice parcial', function () {
    $orden = crearOrdenVigenteParaTrabajo();

    $trabajoId = DB::table('ope_trabajos')->insertGetId([
        'uuid_cliente' => 'uuid-trabajo-para-sesion',
        'orden_id' => $orden['orden_id'],
        'lote_id' => $orden['lote_id'],
        'nro_aplicacion' => 1,
        'estado' => 'abierto',
        'inicio' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pilotoId = DB::table('per_personas')->insertGetId([
        'nombre' => 'Piloto de prueba',
        'rol' => 'piloto',
        'activo' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ope_sesiones')->insert([
        'uuid_cliente' => 'uuid-sesion-duplicada',
        'trabajo_id' => $trabajoId,
        'secuencia' => 1,
        'piloto_id' => $pilotoId,
        'estado' => 'abierto',
        'inicio' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ope_sesiones')->insert([
        'uuid_cliente' => 'uuid-sesion-duplicada',
        'trabajo_id' => $trabajoId,
        'secuencia' => 2,
        'piloto_id' => $pilotoId,
        'estado' => 'abierto',
        'inicio' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);
