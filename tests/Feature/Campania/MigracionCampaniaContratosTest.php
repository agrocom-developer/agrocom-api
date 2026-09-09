<?php

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * ADR 0015 punto 1 (tarea 69, corregido el 8/9/2026): la migración
 * `2026_09_08_100002_add_campania_id_a_com_contratos_table` no solo agrega la
 * columna — migra datos: nace una campaña `2025-2026` (`abierta`) POR CADA
 * CLIENTE que ya tenga contratos, y cada contrato se asigna a la de SU PROPIO
 * cliente. Este test ejercita la migración real (rollback + reinserción de
 * datos "legacy" sin `campania_id` + re-run), no una reimplementación de su
 * lógica.
 */

uses(RefreshDatabase::class);

it('asigna a cada contrato preexistente la campaña 2025-2026 de su propio cliente, sin ninguna fila nula', function () {
    $clienteA = Cliente::query()->create(['razon_social' => 'Cliente Legado A', 'nit' => '100000001']);
    $clienteB = Cliente::query()->create(['razon_social' => 'Cliente Legado B', 'nit' => '100000002']);

    $rutaMigracion = 'database/migrations/2026_09_08_100002_add_campania_id_a_com_contratos_table.php';

    $this->artisan('migrate:rollback', ['--path' => $rutaMigracion, '--realpath' => false])->run();

    expect(Schema::hasColumn('com_contratos', 'campania_id'))->toBeFalse();

    $ahora = now();
    $datosBase = [
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '1000.00',
        'fecha_inicio' => '2026-01-01',
        'estado' => 'vigente',
        'created_at' => $ahora,
        'updated_at' => $ahora,
    ];

    $contratoA1 = DB::table('com_contratos')->insertGetId([...$datosBase, 'cliente_id' => $clienteA->id, 'hectareas_contratadas' => '100.00']);
    $contratoA2 = DB::table('com_contratos')->insertGetId([...$datosBase, 'cliente_id' => $clienteA->id, 'hectareas_contratadas' => '50.00']);
    $contratoB1 = DB::table('com_contratos')->insertGetId([...$datosBase, 'cliente_id' => $clienteB->id, 'hectareas_contratadas' => '80.00']);

    $this->artisan('migrate', ['--path' => $rutaMigracion, '--realpath' => false])->run();

    $filas = DB::table('com_contratos')
        ->whereIn('id', [$contratoA1, $contratoA2, $contratoB1])
        ->get()
        ->keyBy('id');

    expect($filas[$contratoA1]->campania_id)->not->toBeNull()
        ->and($filas[$contratoA2]->campania_id)->not->toBeNull()
        ->and($filas[$contratoB1]->campania_id)->not->toBeNull();

    $campaniaA1 = Campania::query()->findOrFail($filas[$contratoA1]->campania_id);
    $campaniaA2 = Campania::query()->findOrFail($filas[$contratoA2]->campania_id);
    $campaniaB1 = Campania::query()->findOrFail($filas[$contratoB1]->campania_id);

    expect($campaniaA1->id)->toBe($campaniaA2->id)
        ->and($campaniaA1->cliente_id)->toBe($clienteA->id)
        ->and($campaniaA1->codigo)->toBe('2025-2026')
        ->and($campaniaA1->estado->value)->toBe('abierta')
        ->and($campaniaB1->cliente_id)->toBe($clienteB->id)
        ->and($campaniaB1->id)->not->toBe($campaniaA1->id);

    expect(DB::table('com_contratos')->whereNull('campania_id')->count())->toBe(0);
});
