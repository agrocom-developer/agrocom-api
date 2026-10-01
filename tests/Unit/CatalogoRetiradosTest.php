<?php

use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Operaciones\Aplicacion\EliminarTrabajo;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosOrden;
use App\Dominios\Operaciones\Contratos\LecturaRetirosCatalogo;
use App\Dominios\Operaciones\Dominio\CausaCancelacionOrden;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Contratos\LecturaPersonas;
use App\Dominios\Sincronizacion\Aplicacion\ObtenerCatalogoDesdeCursor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * Secciones `ordenes_retiradas` y `trabajos_retirados` de
 * `GET /api/sync/catalogo` (opción B de la propuesta de #312, decisión del
 * dueño). Por el caso de uso real (`ObtenerCatalogoDesdeCursor`) y las lecturas
 * reales de Operaciones y Personal, contra el ESQUEMA REAL (las migraciones, en
 * SQLite en memoria). Lotes y personas, de Comercial y de Personal, quedan
 * vacíos: no son lo que se prueba acá.
 *
 * Lo que se garantiza:
 *
 *   1. Una orden que deja de estar vigente aparece en `ordenes_retiradas` con
 *      su estado; si vuelve a vigente, reaparece en `ordenes[]`.
 *   2. Un trabajo dado de baja, cerrado, de una orden cerrada o reasignado a
 *      otro equipo aparece en `trabajos_retirados` con su motivo, y nunca
 *      también en `trabajos[]`.
 *   3. Una persona solo recibe como retirados trabajos que TUVO: nunca el
 *      `uuid_cliente` de un trabajo de un equipo que no integró (exposición de
 *      datos entre usuarios, CLAUDE.md §Testing).
 *   4. El primer pull (cursor vacío) no trae retirados; un cursor anterior a
 *      estas secciones las trae desde el principio; las dos paginan con el
 *      mismo cursor que el resto.
 */

uses(TestCase::class);

function retiradosEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_ope_ordenes_aplicacion_table',
        'create_ope_orden_lotes_table',
        'create_ope_ordenes_trabajo_table',
        'create_ope_trabajos_table',
        'create_ope_sesiones_table',
        'create_per_equipo_integrantes_table',
    ];

    // Cada archivo se incluye una sola vez por proceso: la migración es una clase anónima.
    static $instancias = [];

    foreach ($migraciones as $nombre) {
        $archivos = glob(database_path("migrations/*_{$nombre}.php")) ?: [];

        if (count($archivos) !== 1) {
            throw new RuntimeException("Se esperaba exactamente una migración {$nombre}.");
        }

        $instancias[$archivos[0]] ??= require $archivos[0];

        try {
            $instancias[$archivos[0]]->up();
        } catch (QueryException $excepcion) {
            // SQLite no conoce el índice parcial de Postgres de estas migraciones.
            if (! str_contains($excepcion->getMessage(), 'USING btree')) {
                throw $excepcion;
            }
        }
    }
}

function retiradosOrden(string $estado = 'vigente'): int
{
    return DB::table('ope_ordenes_aplicacion')->insertGetId([
        'contrato_id' => 1,
        'nro_aplicacion' => 1,
        'fecha_emision' => '2026-10-01',
        'estado' => $estado,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Por Eloquent, como `AsignarEquiposOrden`: así la bitácora registra a qué
 * equipo se asignó.
 */
function retiradosTrabajo(int $ordenId, int $equipoTrabajoId): Trabajo
{
    return Trabajo::query()->create([
        'uuid_cliente' => (string) Str::uuid(),
        'orden_id' => $ordenId,
        'lote_id' => 3,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '0.00',
        'estado' => 'abierto',
        'inicio' => now(),
        'equipo_trabajo_id' => $equipoTrabajoId,
    ]);
}

function retiradosIntegrante(int $personaId, int $equipoTrabajoId, string $desde = '2026-09-01', ?string $hasta = null): void
{
    DB::table('per_equipo_integrantes')->insert([
        'equipo_trabajo_id' => $equipoTrabajoId,
        'persona_id' => $personaId,
        'rol_equipo' => 'piloto',
        'desde' => $desde,
        'hasta' => $hasta,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** @return array<string, mixed> */
function retiradosPull(?int $personaId, ?string $cursor = null): array
{
    foreach ([LecturaLotes::class, LecturaPersonas::class] as $contrato) {
        test()->mock($contrato)->shouldReceive('listarModificadosDesde')->andReturn([]);
    }

    return app(ObtenerCatalogoDesdeCursor::class)->ejecutar($cursor, $personaId);
}

/**
 * @param  array<string, mixed>  $catalogo
 * @return list<int>
 */
function retiradosIds(array $catalogo, string $seccion): array
{
    /** @var list<array{id: int}> $filas */
    $filas = $catalogo[$seccion];

    return array_column($filas, 'id');
}

beforeEach(function () {
    retiradosEsquema();
    Carbon::setTestNow('2026-10-01 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

// ── 1. Órdenes ─────────────────────────────────────────────────────────────────

test('una orden vigente que se pausa sale en ordenes_retiradas y, al reanudarse, vuelve a ordenes[]', function () {
    $orden = retiradosOrden();
    $maquina = app(MaquinaEstadosOrden::class);

    $primero = retiradosPull(null);
    expect(retiradosIds($primero, 'ordenes'))->toBe([$orden]);

    Carbon::setTestNow('2026-10-01 11:00:00');
    $maquina->pausar(OrdenAplicacion::query()->findOrFail($orden), 'Lluvia');
    $segundo = retiradosPull(null, $primero['cursor']);

    expect($segundo['ordenes'])->toBe([])
        ->and($segundo['ordenes_retiradas'])->toBe([[
            'id' => $orden,
            'estado' => 'pausada',
            'updated_at' => Carbon::parse('2026-10-01 11:00:00')->toIso8601String(),
        ]]);

    Carbon::setTestNow('2026-10-01 12:00:00');
    $maquina->reanudar(OrdenAplicacion::query()->findOrFail($orden));
    $tercero = retiradosPull(null, $segundo['cursor']);

    expect(retiradosIds($tercero, 'ordenes'))->toBe([$orden])
        ->and($tercero['ordenes'][0]['estado'])->toBe('vigente')
        ->and($tercero['ordenes_retiradas'])->toBe([])
        ->and(retiradosPull(null, $tercero['cursor'])['ordenes'])->toBe([]);
});

test('una orden cancelada sale en ordenes_retiradas y sus trabajos abiertos en trabajos_retirados', function () {
    retiradosIntegrante(5, 7);
    $orden = retiradosOrden();
    $trabajo = retiradosTrabajo($orden, 7);
    $primero = retiradosPull(5);
    expect(retiradosIds($primero, 'trabajos'))->toBe([$trabajo->id]);

    Carbon::setTestNow('2026-10-01 11:00:00');
    app(MaquinaEstadosOrden::class)->cancelar(OrdenAplicacion::query()->findOrFail($orden), CausaCancelacionOrden::Cliente, 'Sin pago');
    $segundo = retiradosPull(5, $primero['cursor']);

    expect($segundo['ordenes_retiradas'])->toBe([[
        'id' => $orden,
        'estado' => 'cancelada',
        'updated_at' => Carbon::parse('2026-10-01 11:00:00')->toIso8601String(),
    ]])
        ->and($segundo['trabajos'])->toBe([])
        ->and($segundo['trabajos_retirados'])->toBe([[
            'id' => $trabajo->id,
            'uuid_cliente' => $trabajo->uuid_cliente,
            'motivo' => 'orden_cerrada',
            'updated_at' => Carbon::parse('2026-10-01 11:00:00')->toIso8601String(),
        ]]);
});

// ── 2. Trabajos ────────────────────────────────────────────────────────────────

test('un trabajo dado de baja sale en trabajos_retirados', function () {
    retiradosIntegrante(5, 7);
    $trabajo = retiradosTrabajo(retiradosOrden(), 7);
    $primero = retiradosPull(5);

    Carbon::setTestNow('2026-10-01 11:00:00');
    app(EliminarTrabajo::class)->ejecutar($trabajo);
    $segundo = retiradosPull(5, $primero['cursor']);

    expect($segundo['trabajos'])->toBe([])
        ->and($segundo['trabajos_retirados'])->toBe([[
            'id' => $trabajo->id,
            'uuid_cliente' => $trabajo->uuid_cliente,
            'motivo' => 'dado_de_baja',
            'updated_at' => Carbon::parse('2026-10-01 11:00:00')->toIso8601String(),
        ]]);
});

test('un trabajo cerrado sale en trabajos_retirados y ya no en trabajos[]', function () {
    retiradosIntegrante(5, 7);
    $trabajo = retiradosTrabajo(retiradosOrden(), 7);
    $primero = retiradosPull(5);

    Carbon::setTestNow('2026-10-01 11:00:00');
    $trabajo->estado = 'cerrado';
    $trabajo->save();
    $segundo = retiradosPull(5, $primero['cursor']);

    expect($segundo['trabajos'])->toBe([])
        ->and(array_column($segundo['trabajos_retirados'], 'motivo', 'id'))->toBe([$trabajo->id => 'cerrado']);
});

test('un trabajo reasignado sale en trabajos_retirados de quien lo perdió y en trabajos[] de quien lo ganó', function () {
    retiradosIntegrante(5, 7);
    retiradosIntegrante(6, 8);
    $trabajo = retiradosTrabajo(retiradosOrden(), 7);
    $perdio = retiradosPull(5);
    $gano = retiradosPull(6);
    expect(retiradosIds($perdio, 'trabajos'))->toBe([$trabajo->id])
        ->and($gano['trabajos'])->toBe([]);

    Carbon::setTestNow('2026-10-01 11:00:00');
    $trabajo->equipo_trabajo_id = 8;
    $trabajo->save();

    $perdioDespues = retiradosPull(5, $perdio['cursor']);
    $ganoDespues = retiradosPull(6, $gano['cursor']);

    expect($perdioDespues['trabajos'])->toBe([])
        ->and($perdioDespues['trabajos_retirados'])->toBe([[
            'id' => $trabajo->id,
            'uuid_cliente' => $trabajo->uuid_cliente,
            'motivo' => 'reasignado',
            'updated_at' => Carbon::parse('2026-10-01 11:00:00')->toIso8601String(),
        ]])
        ->and(retiradosIds($ganoDespues, 'trabajos'))->toBe([$trabajo->id])
        ->and($ganoDespues['trabajos_retirados'])->toBe([]);
});

test('un trabajo que pasa a otro equipo de la misma persona no se retira', function () {
    retiradosIntegrante(5, 7);
    retiradosIntegrante(5, 8);
    $trabajo = retiradosTrabajo(retiradosOrden(), 7);
    $primero = retiradosPull(5);

    Carbon::setTestNow('2026-10-01 11:00:00');
    $trabajo->equipo_trabajo_id = 8;
    $trabajo->save();
    $segundo = retiradosPull(5, $primero['cursor']);

    expect(retiradosIds($segundo, 'trabajos'))->toBe([$trabajo->id])
        ->and($segundo['trabajos_retirados'])->toBe([]);
});

// ── 3. Nunca trabajos ajenos ───────────────────────────────────────────────────

test('una persona nunca recibe como retirado un trabajo de un equipo que no integró', function () {
    retiradosIntegrante(5, 7);
    retiradosIntegrante(6, 8);
    $deOtroEquipo = retiradosTrabajo(retiradosOrden(), 8);
    $reasignadoEntreAjenos = retiradosTrabajo(retiradosOrden(), 8);
    $primero = retiradosPull(5);

    Carbon::setTestNow('2026-10-01 11:00:00');
    app(EliminarTrabajo::class)->ejecutar($deOtroEquipo);
    $reasignadoEntreAjenos->equipo_trabajo_id = 9;
    $reasignadoEntreAjenos->save();

    $segundo = retiradosPull(5, $primero['cursor']);

    expect($segundo['trabajos_retirados'])->toBe([])
        ->and(json_encode($segundo))->not->toContain($deOtroEquipo->uuid_cliente)
        ->not->toContain($reasignadoEntreAjenos->uuid_cliente);
});

test('una cuenta sin persona operativa no recibe trabajos retirados', function () {
    retiradosIntegrante(5, 7);
    $trabajo = retiradosTrabajo(retiradosOrden(), 7);
    $primero = retiradosPull(null);

    Carbon::setTestNow('2026-10-01 11:00:00');
    app(EliminarTrabajo::class)->ejecutar($trabajo);

    expect(retiradosPull(null, $primero['cursor'])['trabajos_retirados'])->toBe([]);
});

// ── 4. Cursor ──────────────────────────────────────────────────────────────────

test('el primer pull no trae retirados de lo que el dispositivo nunca tuvo, y deja las dos posiciones en ahora', function () {
    Carbon::setTestNow('2026-10-01 09:00:00');
    retiradosIntegrante(5, 7);
    retiradosOrden('cancelada');
    $dadoDeBaja = retiradosTrabajo(retiradosOrden(), 7);
    $dadoDeBaja->delete();

    Carbon::setTestNow('2026-10-01 10:00:00');
    $primero = retiradosPull(5);

    /** @var array<string, array{u: string, id: int}> $cursor */
    $cursor = json_decode((string) base64_decode($primero['cursor'], true), true);

    expect($primero['ordenes_retiradas'])->toBe([])
        ->and($primero['trabajos_retirados'])->toBe([])
        ->and($cursor['ordenes_retiradas'])->toBe(['u' => Carbon::now()->toIso8601String(), 'id' => 0])
        ->and($cursor['trabajos_retirados'])->toBe(['u' => Carbon::now()->toIso8601String(), 'id' => 0])
        ->and(retiradosPull(5, $primero['cursor'])['trabajos_retirados'])->toBe([]);
});

test('un retiro en el mismo segundo que el primer pull llega en el siguiente: se prefiere repetir a perder', function () {
    retiradosIntegrante(5, 7);
    $dadoDeBaja = retiradosTrabajo(retiradosOrden(), 7);
    $dadoDeBaja->delete();

    $primero = retiradosPull(5);

    expect($primero['trabajos_retirados'])->toBe([])
        ->and(array_column(retiradosPull(5, $primero['cursor'])['trabajos_retirados'], 'motivo', 'id'))
        ->toBe([$dadoDeBaja->id => 'dado_de_baja']);
});

test('un cursor de antes de estas secciones trae los retirados desde el principio', function () {
    retiradosIntegrante(5, 7);
    $cancelada = retiradosOrden('cancelada');
    $dadoDeBaja = retiradosTrabajo(retiradosOrden(), 7);
    $dadoDeBaja->delete();
    $cursorViejo = base64_encode((string) json_encode(['lotes' => ['u' => '2026-09-01T00:00:00+00:00', 'id' => 1]]));

    $pull = retiradosPull(5, $cursorViejo);

    expect(retiradosIds($pull, 'ordenes_retiradas'))->toBe([$cancelada])
        ->and(array_column($pull['trabajos_retirados'], 'motivo', 'id'))->toBe([$dadoDeBaja->id => 'dado_de_baja']);
});

test('las secciones de retirados paginan con el mismo cursor, sin perder ni repetir', function () {
    retiradosIntegrante(5, 7);
    $lectura = app(LecturaRetirosCatalogo::class);
    $ordenes = [retiradosOrden('cancelada'), retiradosOrden('pausada'), retiradosOrden('consumida')];
    $trabajos = [];
    foreach (range(1, 3) as $_) {
        $trabajo = retiradosTrabajo(retiradosOrden(), 7);
        $trabajo->delete();
        $trabajos[] = $trabajo->id;
    }

    $paginaOrdenes = $lectura->ordenesRetiradasDesde(null, null, 2);
    $ultima = $paginaOrdenes[1];
    $restoOrdenes = $lectura->ordenesRetiradasDesde($ultima->updatedAt, $ultima->id, 2);

    $paginaTrabajos = $lectura->trabajosRetiradosDesde(null, null, 2, [7], [7]);
    $ultimo = $paginaTrabajos[1];
    $restoTrabajos = $lectura->trabajosRetiradosDesde($ultimo->updatedAt, $ultimo->id, 2, [7], [7]);

    expect([...array_column(array_map(fn ($o) => $o->toArray(), $paginaOrdenes), 'id'), ...array_column(array_map(fn ($o) => $o->toArray(), $restoOrdenes), 'id')])->toBe($ordenes)
        ->and([...array_column(array_map(fn ($t) => $t->toArray(), $paginaTrabajos), 'id'), ...array_column(array_map(fn ($t) => $t->toArray(), $restoTrabajos), 'id')])->toBe($trabajos);
});

test('una orden emitida nunca sale como retirada: nunca llegó al catálogo', function () {
    retiradosOrden('emitida');
    $cursor = base64_encode((string) json_encode(['lotes' => ['u' => '2026-09-01T00:00:00+00:00', 'id' => 1]]));

    expect(retiradosPull(null, $cursor)['ordenes_retiradas'])->toBe([]);
});
