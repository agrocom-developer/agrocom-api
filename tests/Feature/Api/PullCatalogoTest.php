<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * GET /api/sync/catalogo (espec §2.1, punto 6; TE-06 parcial — ver
 * runs/08-diseno.md) — pull de catálogo con cursor para la app de campo.
 * La demo siembra una orden vigente (lote L-01) y tres lotes (L-01, L-02,
 * L-03); cada test agrega lo que necesita encima de ese punto de partida.
 *
 * Igual que /api/ordenes (HU-03), corre detrás de auth:sanctum — se
 * autentica con el guard directamente porque lo que se prueba es el
 * contrato del cursor, no la autenticación.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);

    $this->actingAs(SecUser::factory()->create(), 'sanctum');
});

/** @param  array<string, mixed>  $atributos */
function crearPersonaDemo(array $atributos = []): PerPersona
{
    return PerPersona::query()->create([
        'nombre' => 'Piloto Demo',
        'rol' => RolOperativoPersona::Piloto,
        'base_id' => null,
        'activo' => true,
        ...$atributos,
    ]);
}

/** @param  array<string, mixed>  $atributos */
function crearOrdenEnLote(string $codigoLote, array $atributos = []): OrdenAplicacion
{
    return OrdenAplicacion::query()->create([
        'contrato_id' => Contrato::query()->value('id'),
        'lote_id' => Lote::query()->where('codigo', $codigoLote)->value('id'),
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-08-26',
        'estado' => EstadoOrdenAplicacion::Emitida,
        ...$atributos,
    ]);
}

it('con desde vacío trae todo lo vigente de las tres secciones (primera sincronización)', function () {
    $persona = crearPersonaDemo();

    $respuesta = $this->getJson('/api/sync/catalogo')->assertOk();

    expect($respuesta->json('ordenes'))->toHaveCount(1)
        ->and($respuesta->json('lotes'))->toHaveCount(3)
        ->and($respuesta->json('personas'))->toHaveCount(1)
        ->and($respuesta->json('personas.0.id'))->toBe($persona->id)
        ->and($respuesta->json('cursor'))->toBeString()
        ->and($respuesta->json('cursor'))->not->toBe('');
});

it('un cursor no decodificable se trata como primera sincronización, no como error', function () {
    $respuesta = $this->getJson('/api/sync/catalogo?desde=esto-no-es-un-cursor-valido')->assertOk();

    expect($respuesta->json('ordenes'))->toHaveCount(1)
        ->and($respuesta->json('lotes'))->toHaveCount(3);
});

it('un cursor con `u` no parseable como fecha se trata como primera sincronización para esa sección, no como error', function () {
    $cursor = base64_encode(json_encode(['ordenes' => ['u' => 'no-es-fecha', 'id' => 1]]));

    $respuesta = $this->getJson('/api/sync/catalogo?desde='.$cursor)->assertOk();

    expect($respuesta->json('ordenes'))->toHaveCount(1)
        ->and($respuesta->json('lotes'))->toHaveCount(3);
});

it('dos pulls sucesivos con el cursor del primero no repiten ningún registro', function () {
    crearPersonaDemo();

    $primero = $this->getJson('/api/sync/catalogo')->assertOk();
    $cursor = $primero->json('cursor');

    $segundo = $this->getJson('/api/sync/catalogo?desde='.$cursor)->assertOk();

    expect($segundo->json('ordenes'))->toBe([])
        ->and($segundo->json('lotes'))->toBe([])
        ->and($segundo->json('personas'))->toBe([]);
});

it('un lote modificado después del cursor aparece en el siguiente pull, sin repetir los demás', function () {
    $primero = $this->getJson('/api/sync/catalogo')->assertOk();
    $cursor = $primero->json('cursor');

    $this->travel(2)->seconds();

    $lote = Lote::query()->where('codigo', 'L-01')->firstOrFail();
    $lote->forceFill(['restricciones' => 'No aplicar cerca de la casa.'])->save();

    $segundo = $this->getJson('/api/sync/catalogo?desde='.$cursor)->assertOk();

    expect(collect($segundo->json('lotes'))->pluck('id')->all())->toBe([$lote->id])
        ->and($segundo->json('lotes.0.restricciones'))->toBe('No aplicar cerca de la casa.');
});

it('una persona creada después del cursor aparece en el siguiente pull', function () {
    $primero = $this->getJson('/api/sync/catalogo')->assertOk();
    $cursor = $primero->json('cursor');

    $this->travel(2)->seconds();

    $persona = crearPersonaDemo(['nombre' => 'Piloto Nuevo']);

    $segundo = $this->getJson('/api/sync/catalogo?desde='.$cursor)->assertOk();

    expect(collect($segundo->json('personas'))->pluck('id')->all())->toBe([$persona->id]);
});

it('una orden que pasa a vigente después del cursor aparece en el siguiente pull', function () {
    $orden = crearOrdenEnLote('L-02', ['estado' => EstadoOrdenAplicacion::Emitida]);

    $primero = $this->getJson('/api/sync/catalogo')->assertOk();
    expect(collect($primero->json('ordenes'))->pluck('id')->all())->not->toContain($orden->id);

    $this->travel(2)->seconds();

    $orden->forceFill(['estado' => EstadoOrdenAplicacion::Vigente])->save();

    $segundo = $this->getJson('/api/sync/catalogo?desde='.$primero->json('cursor'))->assertOk();

    expect(collect($segundo->json('ordenes'))->pluck('id')->all())->toBe([$orden->id])
        ->and($segundo->json('ordenes.0.estado'))->toBe('vigente');
});

it('excluye del catálogo las órdenes que no están vigentes', function () {
    $ordenConsumida = crearOrdenEnLote('L-02', ['estado' => EstadoOrdenAplicacion::Consumida]);
    crearOrdenEnLote('L-03', ['estado' => EstadoOrdenAplicacion::Vencida]);

    $respuesta = $this->getJson('/api/sync/catalogo')->assertOk();

    expect($respuesta->json('ordenes'))->toHaveCount(1)
        ->and(collect($respuesta->json('ordenes'))->pluck('id')->all())->not->toContain($ordenConsumida->id);
});

it('excluye del catálogo un lote borrado lógicamente', function () {
    $lote = Lote::query()->where('codigo', 'L-03')->firstOrFail();
    $lote->delete();

    $respuesta = $this->getJson('/api/sync/catalogo')->assertOk();

    expect($respuesta->json('lotes'))->toHaveCount(2)
        ->and(collect($respuesta->json('lotes'))->pluck('id')->all())->not->toContain($lote->id);
});

it('excluye del siguiente pull una persona borrada lógicamente después de haber sido entregada', function () {
    $persona = crearPersonaDemo();

    $primero = $this->getJson('/api/sync/catalogo')->assertOk();
    expect(collect($primero->json('personas'))->pluck('id')->all())->toBe([$persona->id]);

    $this->travel(2)->seconds();

    $persona->delete();

    $segundo = $this->getJson('/api/sync/catalogo?desde='.$primero->json('cursor'))->assertOk();

    expect($segundo->json('personas'))->toBe([]);
});
