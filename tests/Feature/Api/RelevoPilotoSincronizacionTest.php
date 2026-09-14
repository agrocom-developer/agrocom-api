<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * POST /api/sync — relevo de piloto y cambio de dron (espec §5, HU-07,
 * tarea 20): `dron_id`/`hectarea_inicial_acumulada` en la apertura de una
 * sesión (`Contratos/AperturaSesion`). Ambos OPCIONALES (ver runs/20.md) —
 * mismo criterio de prueba que la tarea 18 le dio a
 * `litros_consumidos`/`litros_sobrante`: confirmar que persisten cuando
 * llegan, y que su ausencia no rompe el flujo ya aceptado.
 *
 * Nombres de función propios (no se reutilizan los de
 * CierreSincronizacionTest.php ni otros archivos de test de sync: Pest
 * declara los helpers de cada archivo como funciones PHP globales).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(SecUser::factory()->create(), 'sanctum');
});

function ordenParaRelevo(): OrdenAplicacion
{
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');

    return OrdenAplicacion::query()
        ->whereHas('ordenLotes', fn ($q) => $q->where('lote_id', $loteId))
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();
}

function pilotoParaRelevo(string $nombre = 'Piloto Relevo'): PerPersona
{
    return PerPersona::query()->create(['nombre' => $nombre, 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
}

/** @return array<string, mixed> */
function registroTrabajoParaRelevo(string $uuidCliente): array
{
    $orden = ordenParaRelevo();

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
function registroSesionParaRelevo(string $uuidCliente, string $trabajoUuidCliente, int $pilotoId, int $secuencia = 1): array
{
    return [
        'tipo' => 'sesion',
        'uuid_cliente' => $uuidCliente,
        'trabajo_uuid_cliente' => $trabajoUuidCliente,
        'secuencia' => $secuencia,
        'piloto_id' => $pilotoId,
        'inicio' => '2026-09-01T10:05:00-04:00',
    ];
}

it('abrir una sesión con dron_id y hectarea_inicial_acumulada persiste ambos', function () {
    $piloto = pilotoParaRelevo();
    $dron = Dron::create(['identificador' => 'DJI-T50-01']);

    $this->postJson('/api/sync', ['registros' => [registroTrabajoParaRelevo('uuid-t-relevo-1')]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [[
        ...registroSesionParaRelevo('uuid-s-relevo-1', 'uuid-t-relevo-1', $piloto->id),
        'dron_id' => $dron->id,
        'hectarea_inicial_acumulada' => '15.30',
    ]]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('aplicado');

    $sesion = Sesion::query()->where('uuid_cliente', 'uuid-s-relevo-1')->firstOrFail();
    expect($sesion->dron_id)->toBe($dron->id)
        ->and($sesion->hectarea_inicial_acumulada)->toBe('15.30');
});

it('abrir una sesión sin dron_id ni hectarea_inicial_acumulada sigue aplicando (ambos opcionales)', function () {
    $piloto = pilotoParaRelevo();

    $this->postJson('/api/sync', ['registros' => [registroTrabajoParaRelevo('uuid-t-relevo-2')]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroSesionParaRelevo('uuid-s-relevo-2', 'uuid-t-relevo-2', $piloto->id),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('aplicado');

    $sesion = Sesion::query()->where('uuid_cliente', 'uuid-s-relevo-2')->firstOrFail();
    expect($sesion->dron_id)->toBeNull()
        ->and($sesion->hectarea_inicial_acumulada)->toBeNull();
});

it('un relevo de piloto abre la segunda sesión con dron distinto de la primera', function () {
    $pilotoSaliente = pilotoParaRelevo('Piloto saliente');
    $pilotoEntrante = pilotoParaRelevo('Piloto entrante');
    $dronUno = Dron::create(['identificador' => 'DJI-AGRAS-01']);
    $dronDos = Dron::create(['identificador' => 'DJI-AGRAS-02']);

    $this->postJson('/api/sync', ['registros' => [registroTrabajoParaRelevo('uuid-t-relevo-3')]])->assertOk();

    $this->postJson('/api/sync', ['registros' => [[
        ...registroSesionParaRelevo('uuid-s-relevo-3a', 'uuid-t-relevo-3', $pilotoSaliente->id, 1),
        'dron_id' => $dronUno->id,
    ]]])->assertOk();

    $this->postJson('/api/sync', ['registros' => [[
        ...registroSesionParaRelevo('uuid-s-relevo-3b', 'uuid-t-relevo-3', $pilotoEntrante->id, 2),
        'dron_id' => $dronDos->id,
        'hectarea_inicial_acumulada' => '8.00',
    ]]])->assertOk();

    $sesionUno = Sesion::query()->where('uuid_cliente', 'uuid-s-relevo-3a')->firstOrFail();
    $sesionDos = Sesion::query()->where('uuid_cliente', 'uuid-s-relevo-3b')->firstOrFail();

    expect($sesionUno->dron_id)->toBe($dronUno->id)
        ->and($sesionDos->dron_id)->toBe($dronDos->id)
        ->and($sesionDos->hectarea_inicial_acumulada)->toBe('8.00');
});

it('abrir una sesión con dron_id inexistente se rechaza sin romper el resto del lote', function () {
    $piloto = pilotoParaRelevo();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaRelevo('uuid-t-relevo-4'),
        [
            ...registroSesionParaRelevo('uuid-s-relevo-4', 'uuid-t-relevo-4', $piloto->id),
            'dron_id' => 999999,
        ],
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('aplicado')
        ->and($respuesta->json('resultados.1.estado'))->toBe('rechazado');

    expect(Sesion::query()->where('uuid_cliente', 'uuid-s-relevo-4')->exists())->toBeFalse();
});
