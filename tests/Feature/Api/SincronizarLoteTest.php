<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * POST /api/sync (espec §2.1, puntos 3 a 5; TE-05, tarea 09) — comportamiento
 * vía HTTP del push en lote. El test de replay explícito de la espec (mismo
 * lote 10 veces, en orden y en desorden) vive aparte, en
 * ReplaySincronizacionTest.php. La demo siembra una orden vigente para el
 * lote L-01 (mismo punto de partida que PullCatalogoTest).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);

    $this->actingAs(SecUser::factory()->create(), 'sanctum');
});

function ordenDemoVigente(): OrdenAplicacion
{
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');

    return OrdenAplicacion::query()
        ->where('lote_id', $loteId)
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();
}

function pilotoDemo(): PerPersona
{
    return PerPersona::query()->create([
        'nombre' => 'Piloto Sync',
        'rol' => RolOperativoPersona::Piloto,
        'activo' => true,
    ]);
}

/** @param  array<string, mixed>  $extra
 * @return array<string, mixed> */
function registroTrabajo(string $uuidCliente, OrdenAplicacion $orden, array $extra = []): array
{
    return [
        'tipo' => 'trabajo',
        'uuid_cliente' => $uuidCliente,
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => $orden->nro_aplicacion,
        'inicio' => '2026-09-01T10:00:00-04:00',
        ...$extra,
    ];
}

/** @param  array<string, mixed>  $extra
 * @return array<string, mixed> */
function registroSesion(string $uuidCliente, string $trabajoUuidCliente, int $pilotoId, array $extra = []): array
{
    return [
        'tipo' => 'sesion',
        'uuid_cliente' => $uuidCliente,
        'trabajo_uuid_cliente' => $trabajoUuidCliente,
        'secuencia' => 1,
        'piloto_id' => $pilotoId,
        'inicio' => '2026-09-01T10:05:00-04:00',
        ...$extra,
    ];
}

it('aplica un lote nuevo con un trabajo y su sesión', function () {
    $orden = ordenDemoVigente();
    $piloto = pilotoDemo();

    $respuesta = $this->postJson('/api/sync', [
        'registros' => [
            registroTrabajo('uuid-t1', $orden),
            registroSesion('uuid-s1', 'uuid-t1', $piloto->id),
        ],
    ])->assertOk();

    expect($respuesta->json('resultados'))->toBe([
        ['uuid_cliente' => 'uuid-t1', 'tipo' => 'trabajo', 'estado' => 'aplicado'],
        ['uuid_cliente' => 'uuid-s1', 'tipo' => 'sesion', 'estado' => 'aplicado'],
    ]);

    expect(Trabajo::query()->where('uuid_cliente', 'uuid-t1')->exists())->toBeTrue()
        ->and(Sesion::query()->where('uuid_cliente', 'uuid-s1')->exists())->toBeTrue();
});

it('un reintento exacto del mismo lote responde duplicado sin crear filas nuevas ni error', function () {
    $orden = ordenDemoVigente();
    $piloto = pilotoDemo();

    $lote = [
        'registros' => [
            registroTrabajo('uuid-t2', $orden),
            registroSesion('uuid-s2', 'uuid-t2', $piloto->id),
        ],
    ];

    $this->postJson('/api/sync', $lote)->assertOk();
    $segunda = $this->postJson('/api/sync', $lote)->assertOk();

    expect($segunda->json('resultados'))->toBe([
        ['uuid_cliente' => 'uuid-t2', 'tipo' => 'trabajo', 'estado' => 'duplicado'],
        ['uuid_cliente' => 'uuid-s2', 'tipo' => 'sesion', 'estado' => 'duplicado'],
    ]);

    expect(Trabajo::query()->where('uuid_cliente', 'uuid-t2')->count())->toBe(1)
        ->and(Sesion::query()->where('uuid_cliente', 'uuid-s2')->count())->toBe(1);
});

it('un registro inválido no bloquea el resto del lote', function () {
    $orden = ordenDemoVigente();
    $piloto = pilotoDemo();

    $respuesta = $this->postJson('/api/sync', [
        'registros' => [
            // Sin 'inicio': AperturaTrabajo::intentarDesdeArreglo() lo rechaza.
            ['tipo' => 'trabajo', 'uuid_cliente' => 'uuid-invalido', 'orden_id' => $orden->id, 'lote_id' => $orden->lote_id, 'nro_aplicacion' => 1],
            registroTrabajo('uuid-t3', $orden),
            registroSesion('uuid-s3', 'uuid-t3', $piloto->id),
        ],
    ])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull()
        ->and($respuesta->json('resultados.1.estado'))->toBe('aplicado')
        ->and($respuesta->json('resultados.2.estado'))->toBe('aplicado');

    expect(Trabajo::query()->where('uuid_cliente', 'uuid-invalido')->exists())->toBeFalse();
});

it('una sesión resuelve su trabajo aunque venga antes en el arreglo (desorden)', function () {
    $orden = ordenDemoVigente();
    $piloto = pilotoDemo();

    $respuesta = $this->postJson('/api/sync', [
        'registros' => [
            registroSesion('uuid-s4', 'uuid-t4', $piloto->id),
            registroTrabajo('uuid-t4', $orden),
        ],
    ])->assertOk();

    // El orden de la RESPUESTA respeta el de ENTRADA, no el de procesamiento.
    expect($respuesta->json('resultados.0.tipo'))->toBe('sesion')
        ->and($respuesta->json('resultados.0.estado'))->toBe('aplicado')
        ->and($respuesta->json('resultados.1.tipo'))->toBe('trabajo')
        ->and($respuesta->json('resultados.1.estado'))->toBe('aplicado');

    $trabajo = Trabajo::query()->where('uuid_cliente', 'uuid-t4')->firstOrFail();
    $sesion = Sesion::query()->where('uuid_cliente', 'uuid-s4')->firstOrFail();

    expect($sesion->trabajo_id)->toBe($trabajo->id);
});

it('una sesión que referencia un trabajo inexistente se rechaza sin romper el resto del lote', function () {
    $orden = ordenDemoVigente();
    $piloto = pilotoDemo();

    $respuesta = $this->postJson('/api/sync', [
        'registros' => [
            registroSesion('uuid-s5', 'uuid-trabajo-que-no-existe', $piloto->id),
            registroTrabajo('uuid-t5', $orden),
        ],
    ])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull()
        ->and($respuesta->json('resultados.1.estado'))->toBe('aplicado');

    expect(Sesion::query()->where('uuid_cliente', 'uuid-s5')->exists())->toBeFalse();
});

it('exige que el cuerpo traiga el arreglo registros', function () {
    $this->postJson('/api/sync', [])->assertStatus(422);
});
