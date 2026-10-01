<?php

use App\Dominios\Operaciones\Contratos\LecturaTrabajosAsignados;
use App\Dominios\Operaciones\Contratos\RegistroCondiciones;
use App\Dominios\Operaciones\Contratos\TrabajoAsignadoCatalogo;
use App\Dominios\Operaciones\Dominio\LimitesEfectivos;
use Illuminate\Database\QueryException;
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
 */

uses(TestCase::class);

function catalogoTrabajosEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_ope_ordenes_trabajo_table',
        'create_ope_trabajos_table',
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

/** @return array<int, array<string, mixed>> por id de trabajo */
function catalogoTrabajosPorId(): array
{
    $trabajos = app(LecturaTrabajosAsignados::class)->listarModificadosDesde(null, null, 100);

    return collect($trabajos)
        ->mapWithKeys(fn (TrabajoAsignadoCatalogo $trabajo): array => [$trabajo->id => $trabajo->toArray()])
        ->all();
}

beforeEach(function () {
    catalogoTrabajosEsquema();
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
