<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Recarga;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * POST /api/sync — recargas del dron: batería, temperatura, litros y motivo
 * de retraso por caldo (HU-13, tarea 23). Temperatura de batería > 50°C
 * persiste con `alerta_temperatura = true` — nunca rechaza el registro
 * (a diferencia de `condiciones`, que sí rechaza fuera de rango sin
 * observación firmada).
 *
 * Nombres de función propios (no se reutilizan los de otros test files de
 * sync — Pest declara los helpers de cada archivo como funciones PHP
 * globales, ver docblock de `CierreSincronizacionTest.php`).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(SecUser::factory()->create(), 'sanctum');
});

function ordenParaRecarga(): OrdenAplicacion
{
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');

    return OrdenAplicacion::query()
        ->whereHas('ordenLotes', fn ($q) => $q->where('lote_id', $loteId))
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();
}

function pilotoParaRecarga(): PerPersona
{
    return PerPersona::query()->create(['nombre' => 'Piloto Recarga', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
}

/** @return array<string, mixed> */
function registroTrabajoParaRecarga(string $uuidCliente): array
{
    $orden = ordenParaRecarga();

    return [
        'tipo' => 'trabajo',
        'uuid_cliente' => $uuidCliente,
        'orden_id' => $orden->id,
        'lote_id' => (int) $orden->ordenLotes()->value('lote_id'),
        'nro_aplicacion' => $orden->nro_aplicacion,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ];
}

/** @return array<string, mixed> */
function registroSesionParaRecarga(string $uuidCliente, string $trabajoUuidCliente, int $pilotoId): array
{
    return [
        'tipo' => 'sesion',
        'uuid_cliente' => $uuidCliente,
        'trabajo_uuid_cliente' => $trabajoUuidCliente,
        'secuencia' => 1,
        'piloto_id' => $pilotoId,
        'inicio' => '2026-09-01T10:05:00-04:00',
    ];
}

/** @return array<string, mixed> */
function registroRecargaSync(string $uuidCliente, string $sesionUuidCliente, array $sobrescribir = []): array
{
    return array_merge([
        'tipo' => 'recarga',
        'uuid_cliente' => $uuidCliente,
        'sesion_uuid_cliente' => $sesionUuidCliente,
        'secuencia' => 1,
        'litros_caldo' => '30.00',
        'bateria_saliente_id' => 'BAT-01',
        'temperatura_bateria_c' => '35.00',
        'hora' => '2026-09-01T10:15:00-04:00',
    ], $sobrescribir);
}

/*
 * ── Criterio 1: sesión inexistente → rechazado ──
 */

it('una recarga que referencia una sesión inexistente se rechaza sin romper el resto del lote', function () {
    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroRecargaSync('uuid-recarga-fantasma', 'uuid-sesion-que-no-existe'),
        registroTrabajoParaRecarga('uuid-t1'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull()
        ->and($respuesta->json('resultados.1.estado'))->toBe('aplicado');

    expect(Recarga::query()->where('uuid_cliente', 'uuid-recarga-fantasma')->exists())->toBeFalse();
});

/*
 * ── Criterio 2: temperatura ≤ 50°C → aplicado, sin alerta ──
 */

it('recarga con temperatura de batería dentro de rango se aplica sin alerta', function () {
    $piloto = pilotoParaRecarga();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaRecarga('uuid-t2'),
        registroSesionParaRecarga('uuid-s2', 'uuid-t2', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroRecargaSync('uuid-recarga-2', 'uuid-s2', ['temperatura_bateria_c' => '50.00']),
    ]])->assertOk();

    expect($respuesta->json('resultados.0'))->toBe([
        'uuid_cliente' => 'uuid-recarga-2', 'tipo' => 'recarga', 'estado' => 'aplicado',
    ]);

    $recarga = Recarga::query()->where('uuid_cliente', 'uuid-recarga-2')->firstOrFail();
    expect($recarga->alerta_temperatura)->toBeFalse();
});

/*
 * ── Criterio 3: temperatura > 50°C → aplicado igual, con alerta ──
 */

it('recarga con temperatura de batería por encima de 50°C se aplica igual, con alerta', function () {
    $piloto = pilotoParaRecarga();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaRecarga('uuid-t3'),
        registroSesionParaRecarga('uuid-s3', 'uuid-t3', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroRecargaSync('uuid-recarga-3', 'uuid-s3', ['temperatura_bateria_c' => '50.01']),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('aplicado');

    $recarga = Recarga::query()->where('uuid_cliente', 'uuid-recarga-3')->firstOrFail();
    expect($recarga->alerta_temperatura)->toBeTrue();
});

/*
 * ── Criterio 4: motivo_retraso_caldo + hora_retraso → persistidos ──
 */

it('recarga con motivo_retraso_caldo y hora_retraso los persiste correctamente', function () {
    $piloto = pilotoParaRecarga();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaRecarga('uuid-t4'),
        registroSesionParaRecarga('uuid-s4', 'uuid-t4', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroRecargaSync('uuid-recarga-4', 'uuid-s4', [
            'motivo_retraso_caldo' => 'filtro_tapado',
            'hora_retraso' => '2026-09-01T10:12:00-04:00',
        ]),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('aplicado');

    $recarga = Recarga::query()->where('uuid_cliente', 'uuid-recarga-4')->firstOrFail();
    expect($recarga->motivo_retraso_caldo)->toBe('filtro_tapado')
        ->and($recarga->hora_retraso?->toIso8601String())->not->toBeNull();
});

/*
 * ── Criterio 5: sin motivo de retraso (caso normal) → ambos campos null ──
 */

it('recarga sin motivo de retraso deja motivo_retraso_caldo y hora_retraso en null', function () {
    $piloto = pilotoParaRecarga();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaRecarga('uuid-t5'),
        registroSesionParaRecarga('uuid-s5', 'uuid-t5', $piloto->id),
    ]])->assertOk();

    $this->postJson('/api/sync', ['registros' => [
        registroRecargaSync('uuid-recarga-5', 'uuid-s5'),
    ]])->assertOk();

    $recarga = Recarga::query()->where('uuid_cliente', 'uuid-recarga-5')->firstOrFail();
    expect($recarga->motivo_retraso_caldo)->toBeNull()
        ->and($recarga->hora_retraso)->toBeNull();
});

/*
 * ── Criterio 6: reintento idempotente → duplicado, sin segunda fila ──
 */

it('reintentar el mismo registro de recarga responde duplicado sin crear una segunda fila', function () {
    $piloto = pilotoParaRecarga();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaRecarga('uuid-t6'),
        registroSesionParaRecarga('uuid-s6', 'uuid-t6', $piloto->id),
    ]])->assertOk();

    $recarga = ['registros' => [registroRecargaSync('uuid-recarga-6', 'uuid-s6')]];
    $this->postJson('/api/sync', $recarga)->assertOk();
    $segunda = $this->postJson('/api/sync', $recarga)->assertOk();

    expect($segunda->json('resultados.0.estado'))->toBe('duplicado')
        ->and(Recarga::query()->where('uuid_cliente', 'uuid-recarga-6')->count())->toBe(1);
});

it('litros_combustible_generador es opcional: sin él, queda null', function () {
    $piloto = pilotoParaRecarga();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaRecarga('uuid-t7'),
        registroSesionParaRecarga('uuid-s7', 'uuid-t7', $piloto->id),
    ]])->assertOk();

    $this->postJson('/api/sync', ['registros' => [
        registroRecargaSync('uuid-recarga-7', 'uuid-s7', ['litros_combustible_generador' => '5.50']),
    ]])->assertOk();

    $recarga = Recarga::query()->where('uuid_cliente', 'uuid-recarga-7')->firstOrFail();
    expect((float) $recarga->litros_combustible_generador)->toBe(5.5);

    $this->postJson('/api/sync', ['registros' => [
        registroRecargaSync('uuid-recarga-7b', 'uuid-s7'),
    ]])->assertOk();

    $sinCombustible = Recarga::query()->where('uuid_cliente', 'uuid-recarga-7b')->firstOrFail();
    expect($sinCombustible->litros_combustible_generador)->toBeNull();
});
