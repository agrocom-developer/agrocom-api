<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Aplicacion\AsignarEquiposOrden;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/*
 * GET /api/sync/catalogo (espec §2.1, punto 6; TE-06 parcial — ver
 * runs/08-diseno.md) — pull de catálogo con cursor para la app de campo.
 * La demo siembra una orden vigente (lote L-01), tres lotes (L-01, L-02,
 * L-03) y la cuadrilla de `PersonalDemoSeeder`; cada test agrega lo que
 * necesita encima de ese punto de partida. Las aserciones sobre personas van
 * contra `PerPersona::count()` y no contra un número escrito a mano: lo que
 * este endpoint promete es traer TODAS, no traer siete.
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

/**
 * Trabajo asignado desde el panel (HU-70, tarea 85): pasa por el caso de uso
 * real `AsignarEquiposOrden`, no un `Trabajo::create()` directo — así el
 * `uuid_cliente` propio (`Str::uuid()`) y el `equipo_trabajo_id` quedan
 * exactamente como los deja el flujo real, sección `trabajos` del catálogo.
 */
function crearTrabajoAsignadoDemo(): Trabajo
{
    $orden = OrdenAplicacion::query()->where('estado', EstadoOrdenAplicacion::Vigente)->firstOrFail();
    $base = PerBase::create(['nombre' => 'Base catálogo '.uniqid()]);
    $equipo = EquipoTrabajo::create([
        'codigo' => 'EQ-CAT-'.uniqid(),
        'base_id' => $base->id,
        'estado' => EstadoEquipoTrabajo::Activo,
        'desde' => now()->subYear()->toDateString(),
        'hasta' => null,
    ]);

    return app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipo->id, 'hectareas' => '10.00'],
    ])[0];
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
        ->and($respuesta->json('personas'))->toHaveCount(PerPersona::query()->count())
        ->and(collect($respuesta->json('personas'))->pluck('id'))->toContain($persona->id)
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

it('un trabajo asignado desde el panel aparece en la sección trabajos con su uuid_cliente y su equipo', function () {
    $trabajo = crearTrabajoAsignadoDemo();

    $respuesta = $this->getJson('/api/sync/catalogo')->assertOk();

    expect($respuesta->json('trabajos'))->toHaveCount(1)
        ->and($respuesta->json('trabajos.0.uuid_cliente'))->toBe($trabajo->uuid_cliente)
        ->and($respuesta->json('trabajos.0.equipo_trabajo_id'))->toBe($trabajo->equipo_trabajo_id)
        ->and($respuesta->json('trabajos.0.hectareas_declaradas'))->toBe('10.00');
});

it('un segundo pull con el cursor devuelto no repite el trabajo ya entregado', function () {
    crearTrabajoAsignadoDemo();

    $primero = $this->getJson('/api/sync/catalogo')->assertOk();
    expect($primero->json('trabajos'))->toHaveCount(1);

    $segundo = $this->getJson('/api/sync/catalogo?desde='.$primero->json('cursor'))->assertOk();

    expect($segundo->json('trabajos'))->toBe([]);
});

it('excluye de la sección trabajos los que nacen por sync, sin equipo asignado', function () {
    $orden = OrdenAplicacion::query()->where('estado', EstadoOrdenAplicacion::Vigente)->firstOrFail();

    Trabajo::create([
        'uuid_cliente' => (string) Str::uuid(),
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'estado' => 'abierto',
        'inicio' => now(),
    ]);

    $respuesta = $this->getJson('/api/sync/catalogo')->assertOk();

    expect($respuesta->json('trabajos'))->toBe([]);
});

it('excluye del siguiente pull una persona borrada lógicamente después de haber sido entregada', function () {
    $persona = crearPersonaDemo();

    $primero = $this->getJson('/api/sync/catalogo')->assertOk();
    expect(collect($primero->json('personas'))->pluck('id')->all())->toContain($persona->id);

    $this->travel(2)->seconds();

    $persona->delete();

    $segundo = $this->getJson('/api/sync/catalogo?desde='.$primero->json('cursor'))->assertOk();

    expect($segundo->json('personas'))->toBe([]);
});
