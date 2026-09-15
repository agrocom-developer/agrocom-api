<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Prueba obligatoria de la espec §2.1 aplicada a `estadia_entrada`/
 * `estadia_salida` (HU-51, tarea 74) — mismo criterio que
 * `tests/Feature/Api/ReplaySincronizacionTest.php` (TE-05): el mismo lote
 * reproducido diez veces, en orden y en desorden, deja el estado final
 * idéntico. Sin `DemoSeeder`: la estadía no depende de un catálogo de
 * órdenes/lotes previamente sincronizado (a diferencia de `trabajo`), así que
 * alcanza con un equipo y un campo propios.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(SecUser::factory()->create(), 'sanctum');
});

function equipoTrabajoParaReplayEstadia(): EquipoTrabajo
{
    $base = PerBase::query()->create(['nombre' => 'Base Replay']);

    return EquipoTrabajo::query()->create([
        'codigo' => 'EQ-REPLAY-'.uniqid(),
        'base_id' => $base->id,
        'estado' => 'activo',
        'desde' => '2026-01-01',
    ]);
}

function propiedadParaReplayEstadia(): Propiedad
{
    $cliente = Cliente::query()->create(['razon_social' => 'Cliente Replay', 'tipo_persona' => 'juridica']);

    return Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
}

it('el mismo lote de estadia_entrada/estadia_salida aplicado 10 veces, en orden y en desorden, deja un estado final idéntico', function () {
    $equipo = equipoTrabajoParaReplayEstadia();
    $propiedad = propiedadParaReplayEstadia();

    $entrada = [
        'tipo' => 'estadia_entrada',
        'uuid_cliente' => 'uuid-replay-estadia',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T08:00:00-04:00',
    ];

    $salida = [
        'tipo' => 'estadia_salida',
        'uuid_cliente' => 'uuid-replay-estadia-salida',
        'estadia_uuid_cliente' => 'uuid-replay-estadia',
        'salida' => '2026-09-03T08:00:00-04:00',
    ];

    $enOrden = ['registros' => [$entrada, $salida]];
    // Desorden: la salida antes que su propia entrada en el arreglo — el
    // orden causal fijo de SincronizarLote la aplica igual al final.
    $enDesorden = ['registros' => [$salida, $entrada]];

    $secuenciaDeLotes = [
        $enOrden, $enOrden, $enDesorden, $enOrden, $enDesorden,
        $enDesorden, $enOrden, $enDesorden, $enOrden, $enDesorden,
    ];

    foreach ($secuenciaDeLotes as $indice => $lote) {
        $respuesta = $this->postJson('/api/sync', $lote)->assertOk();

        $estadoEsperado = $indice === 0 ? 'aplicado' : 'duplicado';
        foreach ($respuesta->json('resultados') as $resultado) {
            expect($resultado['estado'])->toBe($estadoEsperado);
        }

        // El estado final tiene que ser idéntico después de CADA repetición:
        // ninguna pasada intermedia puede crear una fila de más.
        expect(EstadiaHacienda::query()->count())->toBe(1);
    }

    $estadiaFinal = EstadiaHacienda::query()->where('uuid_cliente', 'uuid-replay-estadia')->firstOrFail();

    expect($estadiaFinal->equipo_trabajo_id)->toBe($equipo->id)
        ->and($estadiaFinal->propiedad_id)->toBe($propiedad->id)
        ->and($estadiaFinal->salida)->not->toBeNull()
        ->and($estadiaFinal->cierre_uuid_cliente)->toBe('uuid-replay-estadia-salida');
});

it('un estadia_entrada rechazado por equipo ya abierto no frena el resto del lote', function () {
    $equipo = equipoTrabajoParaReplayEstadia();
    $propiedad = propiedadParaReplayEstadia();

    $primeraEntrada = [
        'tipo' => 'estadia_entrada',
        'uuid_cliente' => 'uuid-lote-estadia-1',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T08:00:00-04:00',
    ];

    // Mismo equipo, todavía sin cerrar la primera estadía: se rechaza.
    $segundaEntrada = [
        'tipo' => 'estadia_entrada',
        'uuid_cliente' => 'uuid-lote-estadia-2',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-02T08:00:00-04:00',
    ];

    $otroEquipo = equipoTrabajoParaReplayEstadia();
    $entradaOtroEquipo = [
        'tipo' => 'estadia_entrada',
        'uuid_cliente' => 'uuid-lote-estadia-otro-equipo',
        'equipo_trabajo_id' => $otroEquipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-02T08:00:00-04:00',
    ];

    $respuesta = $this->postJson('/api/sync', [
        'registros' => [$primeraEntrada, $segundaEntrada, $entradaOtroEquipo],
    ])->assertOk();

    $resultados = collect($respuesta->json('resultados'))->keyBy('uuid_cliente');

    expect($resultados['uuid-lote-estadia-1']['estado'])->toBe('aplicado')
        ->and($resultados['uuid-lote-estadia-2']['estado'])->toBe('rechazado')
        ->and($resultados['uuid-lote-estadia-otro-equipo']['estado'])->toBe('aplicado');

    expect(EstadiaHacienda::query()->count())->toBe(2);
});
