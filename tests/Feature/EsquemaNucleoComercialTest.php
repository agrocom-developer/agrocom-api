<?php

use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

dataset('tablas del núcleo comercial', [
    'com_clientes',
    'com_cliente_contactos',
    'com_contratos',
    'com_contrato_ventanas',
    'com_campos',
    'com_lotes',
    'ope_ordenes_aplicacion',
]);

it('crea la tabla con soft delete y columnas de auditoría', function (string $tabla) {
    expect(Schema::hasTable($tabla))->toBeTrue()
        ->and(Schema::hasColumns($tabla, [
            'deleted_at',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
})->with('tablas del núcleo comercial');

it('el seeder demo deja una orden de aplicación vigente consultable', function () {
    $this->seed(DemoSeeder::class);

    $orden = DB::table('ope_ordenes_aplicacion')
        ->where('estado', 'vigente')
        ->whereNull('deleted_at')
        ->first();

    expect($orden)->not->toBeNull();

    $lote = DB::table('com_lotes')->where('id', $orden->lote_id)->first();
    $contrato = DB::table('com_contratos')->where('id', $orden->contrato_id)->first();

    expect($lote)->not->toBeNull()
        ->and($contrato)->not->toBeNull()
        ->and($contrato->estado)->toBe('vigente');
});

it('el monto total del contrato demo cuadra exacto desde sus factores', function () {
    $this->seed(DemoSeeder::class);

    // La comparación se hace en la base: en Postgres la aritmética NUMERIC es
    // exacta (invariante 6 — todo monto derivado recalculable y que cuadre).
    $cuadra = DB::table('com_contratos')
        ->where('estado', 'vigente')
        ->whereRaw('monto_total = hectareas_contratadas * aplicaciones_previstas * precio_ha')
        ->exists();

    expect($cuadra)->toBeTrue();
});

it('rechaza una segunda orden vigente para el mismo lote por el índice parcial', function () {
    $this->seed(DemoSeeder::class);

    $orden = DB::table('ope_ordenes_aplicacion')
        ->where('estado', 'vigente')
        ->whereNull('deleted_at')
        ->first();

    DB::table('ope_ordenes_aplicacion')->insert([
        'contrato_id' => $orden->contrato_id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 2,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-08-26',
        'estado' => 'vigente',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);
