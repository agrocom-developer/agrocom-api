<?php

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Contratos\LecturaContrato;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Contrato de lectura `Comercial\Contratos\LecturaContrato` (HU-43, tarea
 * 57): frontera de lectura en sentido inverso a `LecturaActaConformada` —
 * acá es `Operaciones` quien necesita el nombre de cliente de un
 * `contrato_id` propio. Test "unitario" en el sentido de probar la clase
 * directo (sin HTTP) — vive en Feature porque tests/Unit de este repo es
 * PHPUnit puro, sin Eloquent (ver tests/Pest.php).
 *
 * `campaniaId`/`campaniaCodigo` (tarea 81, HU-58): agregados para que
 * `Operaciones\Infraestructura\LecturaDesempenioPersonaEloquent` resuelva la
 * campaña por la ruta exacta `orden → contrato → campania_id` (ADR 0015
 * punto 1), nunca deducida.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

it('resuelve el resumen de un contrato existente, con el nombre del cliente y su campaña', function () {
    $cliente = Cliente::create(['razon_social' => 'Cliente resumen contrato']);
    $campania = Campania::create([
        'cliente_id' => $cliente->id,
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => EstadoCampania::Abierta,
    ]);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'campania_id' => $campania->id,
        'hectareas_contratadas' => '15.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '150.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);

    $resumen = app(LecturaContrato::class)->obtenerResumen($contrato->id);

    expect($resumen)->not->toBeNull()
        ->and($resumen->contratoId)->toBe($contrato->id)
        ->and($resumen->clienteId)->toBe($cliente->id)
        ->and($resumen->clienteNombre)->toBe('Cliente resumen contrato')
        ->and($resumen->campaniaId)->toBe($campania->id)
        ->and($resumen->campaniaCodigo)->toBe('2025-2026');
});

it('resuelve el resumen de un contrato sin campaña asignada, con campaniaId y campaniaCodigo en null', function () {
    $cliente = Cliente::create(['razon_social' => 'Cliente contrato sin campaña']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '15.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '150.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);

    $resumen = app(LecturaContrato::class)->obtenerResumen($contrato->id);

    expect($resumen)->not->toBeNull()
        ->and($resumen->campaniaId)->toBeNull()
        ->and($resumen->campaniaCodigo)->toBeNull();
});

it('devuelve null si el contrato no existe', function () {
    expect(app(LecturaContrato::class)->obtenerResumen(999999))->toBeNull();
});

it('devuelve null si el contrato está borrado (soft delete)', function () {
    $cliente = Cliente::create(['razon_social' => 'Cliente contrato borrado']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '5.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '50.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);
    $contrato->delete();

    expect(app(LecturaContrato::class)->obtenerResumen($contrato->id))->toBeNull();
});
