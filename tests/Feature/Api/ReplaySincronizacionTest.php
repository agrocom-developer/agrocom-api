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
 * Prueba obligatoria de la espec §2.1: "reproducir el mismo lote de
 * sincronización dos, tres, diez veces, en orden y en desorden parcial → el
 * estado final de la base debe ser idéntico." Es el criterio de aceptación
 * propio de la tarea 09 (TE-05) — se aparta de
 * tests/Feature/Api/SincronizarLoteTest.php para que quede visible como la
 * prueba que la espec pide explícitamente, no una más del listado de casos.
 *
 * Nombres de función propios (no se reutilizan los de SincronizarLoteTest):
 * Pest declara los helpers de cada archivo como funciones PHP globales, y
 * reusar un nombre entre archivos choca con "cannot redeclare" o depende del
 * orden de carga — mismo criterio que ya separa `ordenVigenteDePrueba()` de
 * `ordenVigenteParaEscritura()` en el resto de la suite.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);

    $this->actingAs(SecUser::factory()->create(), 'sanctum');
});

function ordenVigenteParaReplay(): OrdenAplicacion
{
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');

    return OrdenAplicacion::query()
        ->whereHas('ordenLotes', fn ($q) => $q->where('lote_id', $loteId))
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();
}

function pilotoParaReplay(): PerPersona
{
    return PerPersona::query()->create([
        'nombre' => 'Piloto Replay',
        'rol' => RolOperativoPersona::Piloto,
        'activo' => true,
    ]);
}

it('el mismo lote aplicado 10 veces, en orden y en desorden parcial, deja un estado final idéntico', function () {
    $orden = ordenVigenteParaReplay();
    $piloto = pilotoParaReplay();

    $trabajo = [
        'tipo' => 'trabajo',
        'uuid_cliente' => 'uuid-replay-trabajo',
        'orden_id' => $orden->id,
        'lote_id' => (int) $orden->ordenLotes()->value('lote_id'),
        'nro_aplicacion' => $orden->nro_aplicacion,
        'hectareas_declaradas' => '12.50',
        'inicio' => '2026-09-01T10:00:00-04:00',
    ];

    $sesion1 = [
        'tipo' => 'sesion',
        'uuid_cliente' => 'uuid-replay-sesion-1',
        'trabajo_uuid_cliente' => 'uuid-replay-trabajo',
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '5.25',
        'inicio' => '2026-09-01T10:05:00-04:00',
    ];

    $sesion2 = [
        'tipo' => 'sesion',
        'uuid_cliente' => 'uuid-replay-sesion-2',
        'trabajo_uuid_cliente' => 'uuid-replay-trabajo',
        'secuencia' => 2,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '7.25',
        'inicio' => '2026-09-01T11:00:00-04:00',
    ];

    $enOrden = ['registros' => [$trabajo, $sesion1, $sesion2]];
    // Desorden parcial: la sesión 2 antes que su propio trabajo en el arreglo.
    $enDesorden = ['registros' => [$sesion2, $trabajo, $sesion1]];

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

        // El estado final tiene que ser idéntico después de CADA repetición,
        // no solo al final: ninguna pasada intermedia puede crear una fila
        // de más.
        expect(Trabajo::query()->count())->toBe(1)
            ->and(Sesion::query()->count())->toBe(2);
    }

    $trabajoFinal = Trabajo::query()->where('uuid_cliente', 'uuid-replay-trabajo')->firstOrFail();
    $sesion1Final = Sesion::query()->where('uuid_cliente', 'uuid-replay-sesion-1')->firstOrFail();
    $sesion2Final = Sesion::query()->where('uuid_cliente', 'uuid-replay-sesion-2')->firstOrFail();

    // Ninguna hectárea duplicada ni sumada de más: el valor declarado en el
    // primer aplicado sobrevive intacto a las nueve repeticiones siguientes.
    expect($trabajoFinal->hectareas_declaradas)->toBe('12.50')
        ->and($sesion1Final->hectareas_declaradas)->toBe('5.25')
        ->and($sesion2Final->hectareas_declaradas)->toBe('7.25')
        ->and($sesion1Final->trabajo_id)->toBe($trabajoFinal->id)
        ->and($sesion2Final->trabajo_id)->toBe($trabajoFinal->id)
        ->and($sesion1Final->secuencia)->toBe(1)
        ->and($sesion2Final->secuencia)->toBe(2);
});
