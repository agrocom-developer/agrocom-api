<?php

use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Operaciones\Aplicacion\ActualizarOrdenTrabajo;
use App\Dominios\Operaciones\Contratos\LecturaOrdenesVigentes;
use App\Dominios\Operaciones\Contratos\LecturaTrabajosAsignados;
use App\Dominios\Operaciones\Contratos\RegistroCondiciones;
use App\Dominios\Operaciones\Contratos\TrabajoAsignadoCatalogo;
use App\Dominios\Operaciones\Dominio\LimitesEfectivos;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajo;
use App\Dominios\Personal\Contratos\LecturaPersonas;
use App\Dominios\Sincronizacion\Aplicacion\ObtenerCatalogoDesdeCursor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * `trabajos[]` de `GET /api/sync/catalogo` (petición de agrocom-field del
 * 1/10/2026), contra el ESQUEMA REAL (las migraciones, en SQLite en memoria) y
 * la implementación real del contrato `LecturaTrabajosAsignados`.
 *
 * Lo que se garantiza:
 *
 *   1. Los 7 límites viajan EFECTIVOS: el de la Orden de Trabajo y, si quedó en
 *      blanco, el default del sistema (`RegistroCondiciones`). Los 4 sin default
 *      quedan `null`. El contrato no es un nivel de la herencia (HU-91).
 *   2. Editar los límites de la Orden de Trabajo después de un pull vuelve a
 *      bajar sus trabajos en el pull incremental siguiente, con el valor nuevo
 *      — sin tocar `ope_trabajos` (invariante 2) — y solo los de esa tanda.
 *   3. Cada operario recibe solo los trabajos de los equipos en los que está
 *      vigente hoy (todos ellos); nunca los de otro equipo, y ninguno si su
 *      cuenta no tiene persona (exposición de datos entre usuarios, CLAUDE.md
 *      §Testing).
 */

uses(TestCase::class);

function catalogoTrabajosEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_ope_ordenes_trabajo_table',
        'create_ope_trabajos_table',
        'create_ope_sesiones_table',
        'create_ope_orden_trabajo_equipos_table',
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

/** @param  array<string, string|null>  $limites */
function catalogoOrdenTrabajo(array $limites = []): int
{
    return DB::table('ope_ordenes_trabajo')->insertGetId([
        'orden_id' => 1,
        'nro_aplicacion' => 1,
        'created_at' => now(),
        'updated_at' => now(),
        ...$limites,
    ]);
}

function catalogoTrabajo(?int $ordenTrabajoId, int $equipoTrabajoId = 7): int
{
    return DB::table('ope_trabajos')->insertGetId([
        'uuid_cliente' => (string) Str::uuid(),
        'orden_id' => 1,
        'lote_id' => 3,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '0.00',
        'estado' => 'abierto',
        'inicio' => now(),
        'equipo_trabajo_id' => $equipoTrabajoId,
        'orden_trabajo_id' => $ordenTrabajoId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Un pull de la sección `trabajos`, desde `$cursor` (`null`: el primero), para
 * todos los equipos que tienen trabajos (el filtro por equipo va en la sección 3), como
 * lo hace `ObtenerCatalogoDesdeCursor`: el cursor siguiente es (`updated_at`,
 * `id`) del último entregado.
 *
 * @param  array{0: string, 1: int}|null  $cursor
 * @return array{trabajos: array<int, array<string, mixed>>, cursor: array{0: string, 1: int}|null} trabajos por id
 */
function catalogoPull(?array $cursor = null): array
{
    $equipos = DB::table('ope_trabajos')->distinct()->pluck('equipo_trabajo_id')->map(fn ($id): int => (int) $id)->all();
    $trabajos = app(LecturaTrabajosAsignados::class)->listarModificadosDesde($cursor[0] ?? null, $cursor[1] ?? null, 100, $equipos);
    $ultimo = $trabajos === [] ? null : $trabajos[array_key_last($trabajos)];

    return [
        'trabajos' => collect($trabajos)
            ->mapWithKeys(fn (TrabajoAsignadoCatalogo $trabajo): array => [$trabajo->id => $trabajo->toArray()])
            ->all(),
        'cursor' => $ultimo === null ? $cursor : [$ultimo->updatedAt, $ultimo->id],
    ];
}

/** @return array<int, array<string, mixed>> por id de trabajo */
function catalogoTrabajosPorId(): array
{
    return catalogoPull()['trabajos'];
}

beforeEach(function () {
    catalogoTrabajosEsquema();
});

afterEach(function () {
    Carbon::setTestNow();
});

// ── 1. Límites efectivos ────────────────────────────────────────────────────────

test('un trabajo con límites propios en su Orden de Trabajo los recibe tal cual', function () {
    $trabajo = catalogoTrabajo(catalogoOrdenTrabajo([
        'humedad_min_pct' => '55.00',
        'humedad_max_pct' => '85.00',
        'viento_max_kmh' => '12.00',
        'temperatura_max_c' => '28.00',
        'altura_vuelo_m' => '3.50',
        'velocidad_vuelo_kmh' => '20.00',
        'ancho_pasada_m' => '7.00',
    ]));

    expect(catalogoTrabajosPorId()[$trabajo])->toMatchArray([
        'humedad_min_pct' => '55.00',
        'humedad_max_pct' => '85.00',
        'viento_max_kmh' => '12.00',
        'temperatura_max_c' => '28.00',
        'altura_vuelo_m' => '3.50',
        'velocidad_vuelo_kmh' => '20.00',
        'ancho_pasada_m' => '7.00',
    ]);
});

test('un límite en blanco en la Orden de Trabajo hereda el default del sistema; sin default, llega null', function () {
    $trabajo = catalogoTrabajo(catalogoOrdenTrabajo(['viento_max_kmh' => '12.00']));

    expect(catalogoTrabajosPorId()[$trabajo])->toMatchArray([
        'viento_max_kmh' => '12.00',
        'temperatura_max_c' => '30.00',
        'humedad_max_pct' => '90.00',
        'humedad_min_pct' => null,
        'altura_vuelo_m' => null,
        'velocidad_vuelo_kmh' => null,
        'ancho_pasada_m' => null,
    ]);
});

test('un trabajo sin Orden de Trabajo recibe los defaults del sistema', function () {
    $trabajo = catalogoTrabajo(null);

    expect(catalogoTrabajosPorId()[$trabajo])->toMatchArray([
        'viento_max_kmh' => '17.00',
        'temperatura_max_c' => '30.00',
        'humedad_max_pct' => '90.00',
        'humedad_min_pct' => null,
    ]);
});

test('el default del sistema sale de las constantes de RegistroCondiciones, su única fuente', function () {
    $limites = LimitesEfectivos::resolver(null, null, null, null, null, null, null);

    expect((float) $limites->vientoMaxKmh)->toBe(RegistroCondiciones::VIENTO_MAX_KMH)
        ->and((float) $limites->temperaturaMaxC)->toBe(RegistroCondiciones::TEMPERATURA_MAX_C)
        ->and((float) $limites->humedadMaxPct)->toBe(RegistroCondiciones::HUMEDAD_MAX_PCT);
});

test('un límite propio cadena vacía cuenta como en blanco', function () {
    $limites = LimitesEfectivos::resolver('', '', '', '', '', '', '');

    expect($limites->vientoMaxKmh)->toBe('17.00')
        ->and($limites->humedadMinPct)->toBeNull()
        ->and($limites->anchoPasadaM)->toBeNull();
});

// ── 2. Editar los límites re-baja el trabajo ───────────────────────────────────

test('editar un límite de la Orden de Trabajo después de un pull vuelve a bajar el trabajo con el valor nuevo', function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    $ordenTrabajo = catalogoOrdenTrabajo(['viento_max_kmh' => '12.00']);
    $trabajo = catalogoTrabajo($ordenTrabajo);
    $otraTanda = catalogoTrabajo(catalogoOrdenTrabajo(['viento_max_kmh' => '14.00']));

    $primero = catalogoPull();
    expect($primero['trabajos'])->toHaveKeys([$trabajo, $otraTanda])
        ->and($primero['trabajos'][$trabajo]['viento_max_kmh'])->toBe('12.00')
        ->and(catalogoPull($primero['cursor'])['trabajos'])->toBe([]);

    Carbon::setTestNow('2026-10-01 11:00:00');
    $trabajoAntes = DB::table('ope_trabajos')->where('id', $trabajo)->value('updated_at');
    app(ActualizarOrdenTrabajo::class)->ejecutar(
        OrdenTrabajo::query()->findOrFail($ordenTrabajo),
        ['viento_max_kmh' => '9.00', 'temperatura_max_c' => '25.00'],
        [],
    );

    $segundo = catalogoPull($primero['cursor']);

    expect(array_keys($segundo['trabajos']))->toBe([$trabajo])
        ->and($segundo['trabajos'][$trabajo])->toMatchArray([
            'viento_max_kmh' => '9.00',
            'temperatura_max_c' => '25.00',
            'updated_at' => Carbon::parse('2026-10-01 11:00:00')->toIso8601String(),
        ])
        ->and(DB::table('ope_trabajos')->where('id', $trabajo)->value('updated_at'))->toBe($trabajoAntes)
        ->and(catalogoPull($segundo['cursor'])['trabajos'])->toBe([]);
});

test('dar de baja la Orden de Trabajo vuelve a bajar el trabajo con los defaults del sistema', function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    $ordenTrabajo = catalogoOrdenTrabajo(['viento_max_kmh' => '12.00']);
    $trabajo = catalogoTrabajo($ordenTrabajo);
    $primero = catalogoPull();

    Carbon::setTestNow('2026-10-01 11:00:00');
    OrdenTrabajo::query()->findOrFail($ordenTrabajo)->delete();

    expect(catalogoPull($primero['cursor'])['trabajos'][$trabajo]['viento_max_kmh'])->toBe('17.00');
});

test('un cambio del propio trabajo posterior a su Orden de Trabajo también avanza el cursor', function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    $trabajo = catalogoTrabajo(catalogoOrdenTrabajo());
    $primero = catalogoPull();

    DB::table('ope_trabajos')->where('id', $trabajo)->update(['updated_at' => '2026-10-01 12:00:00']);

    expect(array_keys(catalogoPull($primero['cursor'])['trabajos']))->toBe([$trabajo]);
});

// ── 3. Filtro por equipo del operario ──────────────────────────────────────────

function catalogoIntegrante(int $personaId, int $equipoTrabajoId, string $desde, ?string $hasta = null): void
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

/**
 * `trabajos[]` del catálogo para el operario `$personaId`, por el caso de uso
 * real; las otras tres secciones (de otros módulos) quedan vacías: no son lo
 * que se prueba acá.
 *
 * @return list<int> ids de trabajo
 */
function catalogoTrabajosDelOperario(?int $personaId): array
{
    foreach ([LecturaOrdenesVigentes::class, LecturaLotes::class, LecturaPersonas::class] as $contrato) {
        test()->mock($contrato)->shouldReceive('listarModificadosDesde')->andReturn([]);
    }

    $catalogo = app(ObtenerCatalogoDesdeCursor::class)->ejecutar(null, $personaId);

    return array_column($catalogo['trabajos'], 'id');
}

test('el operario recibe solo los trabajos de su equipo, nunca los de otro', function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    catalogoIntegrante(personaId: 5, equipoTrabajoId: 7, desde: '2026-09-01');
    catalogoIntegrante(personaId: 6, equipoTrabajoId: 8, desde: '2026-09-01');
    $deSuEquipo = catalogoTrabajo(catalogoOrdenTrabajo(), equipoTrabajoId: 7);
    $deOtroEquipo = catalogoTrabajo(catalogoOrdenTrabajo(), equipoTrabajoId: 8);

    expect(catalogoTrabajosDelOperario(5))->toBe([$deSuEquipo])
        ->and(catalogoTrabajosDelOperario(6))->toBe([$deOtroEquipo]);
});

test('una persona vigente en varios equipos recibe los trabajos de todos', function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    catalogoIntegrante(personaId: 5, equipoTrabajoId: 7, desde: '2026-09-01');
    catalogoIntegrante(personaId: 5, equipoTrabajoId: 8, desde: '2026-09-15', hasta: '2026-10-31');
    $delPrimero = catalogoTrabajo(catalogoOrdenTrabajo(), equipoTrabajoId: 7);
    $delSegundo = catalogoTrabajo(catalogoOrdenTrabajo(), equipoTrabajoId: 8);
    catalogoTrabajo(catalogoOrdenTrabajo(), equipoTrabajoId: 9);

    expect(catalogoTrabajosDelOperario(5))->toBe([$delPrimero, $delSegundo]);
});

test('una integración que no está vigente hoy no da trabajos', function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    catalogoIntegrante(personaId: 5, equipoTrabajoId: 7, desde: '2026-08-01', hasta: '2026-09-30');
    catalogoIntegrante(personaId: 5, equipoTrabajoId: 8, desde: '2026-10-02');
    catalogoIntegrante(personaId: 5, equipoTrabajoId: 9, desde: '2026-10-01', hasta: '2026-10-01');
    catalogoTrabajo(catalogoOrdenTrabajo(), equipoTrabajoId: 7);
    catalogoTrabajo(catalogoOrdenTrabajo(), equipoTrabajoId: 8);
    $hoy = catalogoTrabajo(catalogoOrdenTrabajo(), equipoTrabajoId: 9);

    expect(catalogoTrabajosDelOperario(5))->toBe([$hoy]);
});

test('una integración dada de baja no da trabajos', function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    catalogoIntegrante(personaId: 5, equipoTrabajoId: 7, desde: '2026-09-01');
    DB::table('per_equipo_integrantes')->update(['deleted_at' => now()]);
    catalogoTrabajo(catalogoOrdenTrabajo(), equipoTrabajoId: 7);

    expect(catalogoTrabajosDelOperario(5))->toBe([]);
});

test('una cuenta sin persona operativa recibe trabajos vacío', function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    catalogoIntegrante(personaId: 5, equipoTrabajoId: 7, desde: '2026-09-01');
    catalogoTrabajo(catalogoOrdenTrabajo(), equipoTrabajoId: 7);

    expect(catalogoTrabajosDelOperario(null))->toBe([])
        ->and(catalogoTrabajosDelOperario(99))->toBe([]);
});
