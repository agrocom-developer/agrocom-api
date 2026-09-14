<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Condiciones;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * POST /api/sync — condiciones al iniciar sesión, autoriza o bloquea (HU-06,
 * tarea 17): dentro de rango autoriza sin observación, fuera de rango con
 * observación firmada autoriza igual, fuera de rango sin ella se rechaza
 * (mismo mecanismo de `ResultadoSincronizacion::rechazado()` que ya usa el
 * motor de sync — no se agrega ningún estado nuevo a `EstadoTrabajo`).
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

function ordenParaCondiciones(): OrdenAplicacion
{
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');

    return OrdenAplicacion::query()
        ->whereHas('ordenLotes', fn ($q) => $q->where('lote_id', $loteId))
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();
}

function pilotoParaCondiciones(): PerPersona
{
    return PerPersona::query()->create(['nombre' => 'Piloto Condiciones', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
}

/** @return array<string, mixed> */
function registroTrabajoParaCondiciones(string $uuidCliente): array
{
    $orden = ordenParaCondiciones();

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
function registroSesionParaCondiciones(string $uuidCliente, string $trabajoUuidCliente, int $pilotoId): array
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
function registroCondicionesSync(string $uuidCliente, string $sesionUuidCliente, array $sobrescribir = []): array
{
    return array_merge([
        'tipo' => 'condiciones',
        'uuid_cliente' => $uuidCliente,
        'sesion_uuid_cliente' => $sesionUuidCliente,
        'momento' => 'inicio_sesion',
        'viento_kmh' => '10.00',
        'temperatura_c' => '22.00',
        'humedad_pct' => '60.00',
    ], $sobrescribir);
}

/*
 * ── Criterio de aceptación 1: dentro de rango → aceptado, autorizado sin observación ──
 */

it('condiciones dentro de rango se aceptan autorizadas sin observación', function () {
    $piloto = pilotoParaCondiciones();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCondiciones('uuid-t1'),
        registroSesionParaCondiciones('uuid-s1', 'uuid-t1', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCondicionesSync('uuid-cond-1', 'uuid-s1'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0'))->toBe([
        'uuid_cliente' => 'uuid-cond-1', 'tipo' => 'condiciones', 'estado' => 'aplicado',
    ]);

    $condiciones = Condiciones::query()->where('uuid_cliente', 'uuid-cond-1')->firstOrFail();
    expect($condiciones->autorizado)->toBeTrue()
        ->and($condiciones->observacion_agronomo)->toBeNull()
        ->and($condiciones->firma_observacion)->toBeNull()
        ->and($condiciones->momento)->toBe('inicio_sesion');
});

/*
 * ── Criterio de aceptación 2: fuera de rango + observación firmada → aceptado, autorizado_con_observacion ──
 */

it('condiciones fuera de rango con observacion_agronomo y firma_observacion se aceptan autorizadas con observación', function () {
    $piloto = pilotoParaCondiciones();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCondiciones('uuid-t2'),
        registroSesionParaCondiciones('uuid-s2', 'uuid-t2', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCondicionesSync('uuid-cond-2', 'uuid-s2', [
            'viento_kmh' => '20.00',
            'observacion_agronomo' => 'ventana angosta, se autoriza igual',
            'firma_observacion' => 'Agr. Gómez',
        ]),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('aplicado');

    $condiciones = Condiciones::query()->where('uuid_cliente', 'uuid-cond-2')->firstOrFail();
    expect($condiciones->autorizado)->toBeFalse()
        ->and($condiciones->resultado())->toBe('autorizado_con_observacion')
        ->and($condiciones->observacion_agronomo)->toBe('ventana angosta, se autoriza igual')
        ->and($condiciones->firma_observacion)->toBe('Agr. Gómez');
});

/*
 * ── Criterio de aceptación 3: fuera de rango sin observación → rechazado, sin fila nueva ──
 */

it('condiciones fuera de rango sin observacion_agronomo ni firma_observacion se rechazan sin crear fila', function () {
    $piloto = pilotoParaCondiciones();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCondiciones('uuid-t3'),
        registroSesionParaCondiciones('uuid-s3', 'uuid-t3', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCondicionesSync('uuid-cond-3', 'uuid-s3', ['temperatura_c' => '35.00']),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull();

    expect(Condiciones::query()->where('uuid_cliente', 'uuid-cond-3')->exists())->toBeFalse();
});

it('condiciones fuera de rango con solo uno de los dos campos de observación se rechazan igual', function () {
    $piloto = pilotoParaCondiciones();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCondiciones('uuid-t3b'),
        registroSesionParaCondiciones('uuid-s3b', 'uuid-t3b', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCondicionesSync('uuid-cond-3b', 'uuid-s3b', [
            'humedad_pct' => '95.00',
            'observacion_agronomo' => 'humedad alta',
            // sin firma_observacion
        ]),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado');
    expect(Condiciones::query()->where('uuid_cliente', 'uuid-cond-3b')->exists())->toBeFalse();
});

/*
 * ── Criterio de aceptación 4: reintento del mismo uuid_cliente → duplicado, no crea segunda fila ──
 */

it('reintentar el mismo registro de condiciones responde duplicado sin crear una segunda fila', function () {
    $piloto = pilotoParaCondiciones();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCondiciones('uuid-t4'),
        registroSesionParaCondiciones('uuid-s4', 'uuid-t4', $piloto->id),
    ]])->assertOk();

    $condiciones = ['registros' => [registroCondicionesSync('uuid-cond-4', 'uuid-s4')]];
    $this->postJson('/api/sync', $condiciones)->assertOk();
    $segunda = $this->postJson('/api/sync', $condiciones)->assertOk();

    expect($segunda->json('resultados.0.estado'))->toBe('duplicado')
        ->and(Condiciones::query()->where('uuid_cliente', 'uuid-cond-4')->count())->toBe(1);
});

/*
 * ── Criterio de aceptación 5: condiciones + apertura de sesión en el mismo
 *    lote, en cualquier orden de entrada, se aplican en el orden causal correcto ──
 */

it('condiciones y la apertura de su sesión pueden llegar en el mismo lote, con condiciones ANTES en el arreglo', function () {
    $piloto = pilotoParaCondiciones();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCondicionesSync('uuid-cond-5', 'uuid-s5'),
        registroSesionParaCondiciones('uuid-s5', 'uuid-t5', $piloto->id),
        registroTrabajoParaCondiciones('uuid-t5'),
    ]])->assertOk();

    foreach ($respuesta->json('resultados') as $resultado) {
        expect($resultado['estado'])->toBe('aplicado');
    }

    $condiciones = Condiciones::query()->where('uuid_cliente', 'uuid-cond-5')->firstOrFail();
    $sesion = Sesion::query()->where('uuid_cliente', 'uuid-s5')->firstOrFail();

    expect($condiciones->sesion_id)->toBe($sesion->id)
        ->and($condiciones->trabajo_id)->toBe($sesion->trabajo_id);
});

it('condiciones y la apertura de su sesión pueden llegar en el mismo lote, con condiciones DESPUÉS en el arreglo', function () {
    $piloto = pilotoParaCondiciones();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCondiciones('uuid-t6'),
        registroSesionParaCondiciones('uuid-s6', 'uuid-t6', $piloto->id),
        registroCondicionesSync('uuid-cond-6', 'uuid-s6'),
    ]])->assertOk();

    foreach ($respuesta->json('resultados') as $resultado) {
        expect($resultado['estado'])->toBe('aplicado');
    }

    expect(Condiciones::query()->where('uuid_cliente', 'uuid-cond-6')->exists())->toBeTrue();
});

it('un registro de condiciones que referencia una sesión inexistente se rechaza sin romper el resto del lote', function () {
    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCondicionesSync('uuid-cond-fantasma', 'uuid-sesion-que-no-existe'),
        registroTrabajoParaCondiciones('uuid-t7'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.1.estado'))->toBe('aplicado');
});
