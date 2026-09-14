<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Mezclas\Infraestructura\Eloquent\Mezcla;
use App\Dominios\Mezclas\Infraestructura\Eloquent\MezclaDetalle;
use App\Dominios\Mezclas\Infraestructura\Eloquent\Producto;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * POST /api/sync — registro `mezcla` (espec §7, HU-78, tarea 94, revierte
 * CR-01 del 1/9/2026): el piloto transcribe qué productos y en qué cantidad
 * se cargaron en el caldo al crear una aplicación. Nunca dosis, orden de
 * incorporación ni compatibilidad (§7.1 sigue vigente).
 *
 * Prueba de idempotencia obligatoria de la espec §2.1 (mismo criterio que
 * `ReplayEstadiaHaciendaTest`/`RecepcionCaldoSincronizacionTest`): el mismo
 * lote reproducido diez veces, en orden y en desorden, deja el estado final
 * idéntico.
 *
 * Con `DemoSeeder`: a diferencia de la estadía, la `mezcla` referencia un
 * `trabajo`, que a su vez necesita una orden/lote vigente del catálogo
 * (mismo criterio que `RecepcionCaldoSincronizacionTest`).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(SecUser::factory()->create(), 'sanctum');
});

function ordenParaMezcla(): OrdenAplicacion
{
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');

    return OrdenAplicacion::query()
        ->where('lote_id', $loteId)
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();
}

/** @return array<string, mixed> */
function registroTrabajoParaMezcla(string $uuidCliente): array
{
    $orden = ordenParaMezcla();

    return [
        'tipo' => 'trabajo',
        'uuid_cliente' => $uuidCliente,
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => $orden->nro_aplicacion,
        'inicio' => '2026-09-14T08:00:00-04:00',
    ];
}

/**
 * @param  list<array<string, mixed>>  $productos
 * @return array<string, mixed>
 */
function registroMezclaSync(string $uuidCliente, string $trabajoUuidCliente, array $productos, string $hora = '2026-09-14T07:45:00-04:00'): array
{
    return [
        'tipo' => 'mezcla',
        'uuid_cliente' => $uuidCliente,
        'trabajo_uuid_cliente' => $trabajoUuidCliente,
        'hora' => $hora,
        'productos' => $productos,
    ];
}

/*
 * ── El registro `mezcla` persiste cabecera, detalle y catálogo de productos ──
 */

it('registra una mezcla con sus productos y los persiste en el catálogo', function () {
    $this->postJson('/api/sync', ['registros' => [registroTrabajoParaMezcla('uuid-t1')]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroMezclaSync('uuid-mezcla-1', 'uuid-t1', [
            ['producto' => 'Glifosato 48%', 'cantidad' => '2.50', 'unidad' => 'l'],
            ['producto' => 'Coadyuvante X', 'cantidad' => '500.00', 'unidad' => 'ml'],
        ]),
    ]])->assertOk();

    expect($respuesta->json('resultados.0'))->toBe([
        'uuid_cliente' => 'uuid-mezcla-1', 'tipo' => 'mezcla', 'estado' => 'aplicado',
    ]);

    $trabajo = Trabajo::query()->where('uuid_cliente', 'uuid-t1')->firstOrFail();
    $mezcla = Mezcla::query()->where('uuid_cliente', 'uuid-mezcla-1')->firstOrFail();

    expect($mezcla->trabajo_id)->toBe($trabajo->id)
        ->and(MezclaDetalle::query()->where('mezcla_id', $mezcla->id)->count())->toBe(2)
        ->and(Producto::query()->where('nombre', 'Glifosato 48%')->exists())->toBeTrue()
        ->and(Producto::query()->where('nombre', 'Coadyuvante X')->exists())->toBeTrue();

    $detalleGlifosato = MezclaDetalle::query()
        ->where('mezcla_id', $mezcla->id)
        ->whereRelation('producto', 'nombre', 'Glifosato 48%')
        ->firstOrFail();

    expect($detalleGlifosato->cantidad)->toBe('2.50')->and($detalleGlifosato->unidad)->toBe('l');
});

it('registra VARIAS mezclas para el mismo trabajo y reutiliza el producto ya existente en el catálogo', function () {
    $this->postJson('/api/sync', ['registros' => [registroTrabajoParaMezcla('uuid-t2')]])->assertOk();

    $this->postJson('/api/sync', ['registros' => [
        registroMezclaSync('uuid-mezcla-2a', 'uuid-t2', [['producto' => 'Glifosato 48%', 'cantidad' => '2.00', 'unidad' => 'l']]),
    ]])->assertOk();

    $this->postJson('/api/sync', ['registros' => [
        registroMezclaSync('uuid-mezcla-2b', 'uuid-t2', [['producto' => 'Glifosato 48%', 'cantidad' => '1.00', 'unidad' => 'l']]),
    ]])->assertOk();

    $trabajo = Trabajo::query()->where('uuid_cliente', 'uuid-t2')->firstOrFail();

    expect(Mezcla::query()->where('trabajo_id', $trabajo->id)->count())->toBe(2)
        ->and(Producto::query()->where('nombre', 'Glifosato 48%')->count())->toBe(1);
});

it('la mezcla puede llegar en el mismo lote que la apertura de su trabajo', function () {
    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroMezclaSync('uuid-mezcla-3', 'uuid-t3', [['producto' => 'Fungicida Z', 'cantidad' => '1.00', 'unidad' => 'kg']]),
        registroTrabajoParaMezcla('uuid-t3'),
    ]])->assertOk();

    foreach ($respuesta->json('resultados') as $resultado) {
        expect($resultado['estado'])->toBe('aplicado');
    }

    expect(Mezcla::query()->where('uuid_cliente', 'uuid-mezcla-3')->exists())->toBeTrue();
});

it('una mezcla que referencia un trabajo inexistente se rechaza sin romper el resto del lote', function () {
    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroMezclaSync('uuid-mezcla-fantasma', 'uuid-trabajo-que-no-existe', [['producto' => 'X', 'cantidad' => '1.00', 'unidad' => 'l']]),
        registroTrabajoParaMezcla('uuid-t4'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.1.estado'))->toBe('aplicado');
});

it('una mezcla sin productos se rechaza sin romper el resto del lote', function () {
    $this->postJson('/api/sync', ['registros' => [registroTrabajoParaMezcla('uuid-t5')]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroMezclaSync('uuid-mezcla-vacia', 'uuid-t5', []),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and(Mezcla::query()->where('uuid_cliente', 'uuid-mezcla-vacia')->exists())->toBeFalse();
});

it('una mezcla con un producto de unidad inválida se rechaza completa, sin aplicar los productos válidos', function () {
    $this->postJson('/api/sync', ['registros' => [registroTrabajoParaMezcla('uuid-t6')]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroMezclaSync('uuid-mezcla-invalida', 'uuid-t6', [
            ['producto' => 'Producto válido', 'cantidad' => '1.00', 'unidad' => 'l'],
            ['producto' => 'Producto inválido', 'cantidad' => '1.00', 'unidad' => 'toneladas'],
        ]),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and(Mezcla::query()->where('uuid_cliente', 'uuid-mezcla-invalida')->exists())->toBeFalse()
        ->and(Producto::query()->where('nombre', 'Producto válido')->exists())->toBeFalse();
});

/*
 * ── Idempotencia (invariante 1): replay 10 veces, en orden y en desorden ──
 */

it('el mismo lote de trabajo/mezcla aplicado 10 veces, en orden y en desorden, deja un estado final idéntico', function () {
    $trabajo = registroTrabajoParaMezcla('uuid-replay-mezcla-trabajo');
    $mezcla = registroMezclaSync('uuid-replay-mezcla', 'uuid-replay-mezcla-trabajo', [
        ['producto' => 'Glifosato 48%', 'cantidad' => '2.50', 'unidad' => 'l'],
        ['producto' => 'Coadyuvante X', 'cantidad' => '500.00', 'unidad' => 'ml'],
    ]);

    $enOrden = ['registros' => [$trabajo, $mezcla]];
    // Desorden: la mezcla antes que su propio trabajo — el orden causal fijo
    // de SincronizarLote la aplica igual al final.
    $enDesorden = ['registros' => [$mezcla, $trabajo]];

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
        // ninguna pasada intermedia puede crear una fila (ni de mezcla, ni
        // de detalle, ni de producto) de más.
        expect(Trabajo::query()->count())->toBe(1)
            ->and(Mezcla::query()->count())->toBe(1)
            ->and(MezclaDetalle::query()->count())->toBe(2)
            ->and(Producto::query()->count())->toBe(2);
    }

    $mezclaFinal = Mezcla::query()->where('uuid_cliente', 'uuid-replay-mezcla')->firstOrFail();
    $trabajoFinal = Trabajo::query()->where('uuid_cliente', 'uuid-replay-mezcla-trabajo')->firstOrFail();

    expect($mezclaFinal->trabajo_id)->toBe($trabajoFinal->id);
});
