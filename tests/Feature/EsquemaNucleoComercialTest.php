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
    'com_propiedades',
    'com_campos',
    'com_lotes',
    'com_cultivos',
    'com_lote_campania',
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

dataset('columnas de vuelo retiradas de com_contratos', [
    'adelanto_pct',
    'viento_max_kmh',
    'temperatura_max_c',
    'humedad_min_pct',
    'humedad_max_pct',
    'velocidad_max_kmh',
    'umbral_reporte_avance_ha',
    'altura_vuelo_m',
]);

it('com_contratos ya no tiene parámetros de vuelo propios (HU-91, tarea 106): heredan siempre de la orden o del valor por defecto', function (string $columna) {
    expect(Schema::hasColumn('com_contratos', $columna))->toBeFalse();
})->with('columnas de vuelo retiradas de com_contratos');

it('com_lotes tiene desnivel y limpieza, nullable (HU-73, tarea 89)', function () {
    expect(Schema::hasColumns('com_lotes', ['desnivel', 'limpieza']))->toBeTrue();
});

it('com_clientes tiene ubicación de oficina y logo, nullable (HU-75, tarea 91)', function () {
    expect(Schema::hasColumns('com_clientes', ['ubicacion_oficina', 'logo_path']))->toBeTrue();
});

it('com_propiedades tiene departamento, municipio, localidad y coordenada, nullable (HU-76, tarea 92)', function () {
    expect(Schema::hasColumns('com_propiedades', [
        'departamento',
        'municipio',
        'localidad',
        'latitud',
        'longitud',
    ]))->toBeTrue();
});

it('com_contratos tiene las acomodaciones logísticas, con los booleanos en false por defecto (HU-74, tarea 90)', function () {
    expect(Schema::hasColumns('com_contratos', [
        'brinda_alimentacion',
        'brinda_hospedaje',
        'brinda_combustible',
        'observaciones_logistica',
    ]))->toBeTrue();

    $contratoId = DB::table('com_contratos')->insertGetId([
        'cliente_id' => DB::table('com_clientes')->insertGetId([
            'razon_social' => 'Cliente esquema logística',
            'nit' => '555444333',
            'tipo_persona' => 'juridica',
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'hectareas_contratadas' => '10.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '50.00',
        'monto_total' => '500.00',
        'fecha_inicio' => now()->toDateString(),
        'estado' => 'borrador',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $contrato = DB::table('com_contratos')->where('id', $contratoId)->first();

    expect((bool) $contrato->brinda_alimentacion)->toBeFalse()
        ->and((bool) $contrato->brinda_hospedaje)->toBeFalse()
        ->and((bool) $contrato->brinda_combustible)->toBeFalse()
        ->and($contrato->observaciones_logistica)->toBeNull();
});

it('ope_ordenes_aplicacion tiene el tipo de aplicación, con desarrollo como default (HU-47, tarea 70)', function () {
    expect(Schema::hasColumn('ope_ordenes_aplicacion', 'tipo_aplicacion'))->toBeTrue();

    $clienteId = DB::table('com_clientes')->insertGetId([
        'razon_social' => 'Cliente de prueba tipo_aplicacion',
        'tipo_persona' => 'juridica',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $propiedadId = DB::table('com_propiedades')->insertGetId([
        'cliente_id' => $clienteId,
        'nombre' => 'Propiedad de prueba tipo_aplicacion',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $campoId = DB::table('com_campos')->insertGetId([
        'propiedad_id' => $propiedadId,
        'nombre' => 'Campo de prueba tipo_aplicacion',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $loteId = DB::table('com_lotes')->insertGetId([
        'campo_id' => $campoId,
        'codigo' => 'L-TIPO',
        'hectareas' => '10.00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $contratoId = DB::table('com_contratos')->insertGetId([
        'cliente_id' => $clienteId,
        'hectareas_contratadas' => '10.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '100.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => 'borrador',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Sin `tipo_aplicacion` en el insert: rige el DEFAULT de la migración
    // (columna con `->default('desarrollo')`, aplicado también en SQLite).
    $ordenId = DB::table('ope_ordenes_aplicacion')->insertGetId([
        'contrato_id' => $contratoId,
        'lote_id' => $loteId,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => 'emitida',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('ope_ordenes_aplicacion')->where('id', $ordenId)->value('tipo_aplicacion'))->toBe('desarrollo');
});

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
