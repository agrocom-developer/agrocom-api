<?php

use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Operaciones\Aplicacion\EliminarTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Sincronizacion\Aplicacion\SincronizarLote;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * Registrar lo ya volado sobre un trabajo que el panel dio de baja mientras el
 * equipo lo volaba sin señal (decisión del dueño del 2/10/2026). Por el motor
 * de sync real (`SincronizarLote` → `EscrituraSincronizacionEloquent`), contra
 * el ESQUEMA REAL (las migraciones, en SQLite en memoria).
 *
 * Lo que se garantiza:
 *
 *   1. Con una sesión abierta ANTES de la baja, se aceptan sus condiciones,
 *      su incidencia, su cierre y el cierre del trabajo con su imagen de
 *      campo. El trabajo sigue dado de baja.
 *   2. Las condiciones de esa sesión se deciden con los límites de la Orden
 *      de Trabajo, no con los defaults.
 *   3. Una sesión NUEVA después de la baja se sigue rechazando.
 *   4. Reenviar los mismos registros no duplica nada (idempotencia por
 *      `uuid_cliente`, invariante 1).
 */

uses(TestCase::class);

function bajaEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_ope_ordenes_trabajo_table',
        'create_ope_trabajos_table',
        'create_ope_sesiones_table',
        'create_ope_condiciones_table',
        'create_ope_evidencias_table',
        'create_ope_incidencias_table',
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

    // La idempotencia de `condiciones` e `incidencia` la da el índice único
    // parcial de `uuid_cliente`, que la migración crea con `USING btree`
    // (solo Postgres) y SQLite saltea arriba. Se recrea acá el mismo índice
    // sin esa cláusula, para probar el reenvío contra la garantía real.
    foreach (['ope_condiciones', 'ope_incidencias'] as $tabla) {
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS {$tabla}_uuid_cliente_unico ON {$tabla} (uuid_cliente) WHERE deleted_at IS NULL");
    }
}

/**
 * Trabajo asignado (con Orden de Trabajo de viento máximo 12) y una sesión
 * abierta del piloto 5, ambos creados ANTES de la baja.
 *
 * @return array{trabajo: Trabajo, sesion: Sesion}
 */
function bajaTrabajoConSesionAbierta(): array
{
    $ordenTrabajoId = DB::table('ope_ordenes_trabajo')->insertGetId([
        'orden_id' => 1,
        'nro_aplicacion' => 1,
        'viento_max_kmh' => '12.00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $trabajo = Trabajo::query()->create([
        'uuid_cliente' => (string) Str::uuid(),
        'orden_id' => 1,
        'lote_id' => 3,
        'orden_trabajo_id' => $ordenTrabajoId,
        'equipo_trabajo_id' => 7,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '0.00',
        'estado' => 'abierto',
        'inicio' => now(),
    ]);

    $sesion = Sesion::query()->create([
        'uuid_cliente' => (string) Str::uuid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => 5,
        'estado' => 'abierto',
        'inicio' => now(),
    ]);

    return ['trabajo' => $trabajo, 'sesion' => $sesion];
}

function bajaEvidencia(string $tipo): string
{
    $uuid = (string) Str::uuid();

    DB::table('ope_evidencias')->insert([
        'uuid_cliente' => $uuid,
        'tipo' => $tipo,
        'archivo_url' => "evidencias/{$uuid}.jpg",
        'hash' => str_repeat('a', 64),
        'fecha' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $uuid;
}

/**
 * @param  list<array<string, mixed>>  $registros
 * @return array<string, string> estado por `uuid_cliente`
 */
function bajaSync(array $registros, ?int $operarioPersonaId = 5): array
{
    test()->mock(LecturaLotes::class)->shouldReceive('obtenerPorId')->andReturn(null);

    $resultados = app(SincronizarLote::class)->ejecutar($registros, $operarioPersonaId);

    return array_column($resultados, 'estado', 'uuid_cliente');
}

/**
 * Lo que la app encola sobre la sesión y el trabajo en curso: condiciones,
 * incidencia, cierre de la sesión y cierre del trabajo con su imagen.
 *
 * @param  array{trabajo: Trabajo, sesion: Sesion}  $enCurso
 * @return list<array<string, mixed>>
 */
function bajaRegistrosDeCierre(array $enCurso): array
{
    return [
        [
            'tipo' => 'condiciones',
            'uuid_cliente' => 'condiciones-1',
            'sesion_uuid_cliente' => $enCurso['sesion']->uuid_cliente,
            'momento' => 'inicio_sesion',
            'viento_kmh' => '10.00',
            'temperatura_c' => '25.00',
            'humedad_pct' => '60.00',
        ],
        [
            'tipo' => 'incidencia',
            'uuid_cliente' => 'incidencia-1',
            'sesion_uuid_cliente' => $enCurso['sesion']->uuid_cliente,
            'tipo_incidencia' => 'otro',
            'descripcion' => 'Viento cambiante',
            'hora' => '2026-10-02T10:30:00+00:00',
            'evidencia_foto_uuid_cliente' => bajaEvidencia('foto_incidencia'),
        ],
        [
            'tipo' => 'cierre_sesion',
            'uuid_cliente' => 'cierre-sesion-1',
            'sesion_uuid_cliente' => $enCurso['sesion']->uuid_cliente,
            'fin' => '2026-10-02T11:00:00+00:00',
            'motivo_cierre' => 'completado',
            'hectareas_declaradas' => '40.50',
            'litros_consumidos' => '120.00',
        ],
        [
            'tipo' => 'cierre_trabajo',
            'uuid_cliente' => 'cierre-trabajo-1',
            'trabajo_uuid_cliente' => $enCurso['trabajo']->uuid_cliente,
            'fin' => '2026-10-02T11:30:00+00:00',
            'litros_sobrante' => '8.00',
            'evidencia_imagen_campo_uuid_cliente' => bajaEvidencia('imagen_campo'),
        ],
    ];
}

beforeEach(function () {
    bajaEsquema();
    Carbon::setTestNow('2026-10-02 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

// ── 1 y 2. Lo ya volado se registra ───────────────────────────────────────────

test('con la sesión abierta antes de la baja se aceptan sus condiciones, su incidencia, su cierre y el cierre del trabajo', function () {
    $enCurso = bajaTrabajoConSesionAbierta();
    app(EliminarTrabajo::class)->ejecutar($enCurso['trabajo']);

    $estados = bajaSync(bajaRegistrosDeCierre($enCurso));

    $trabajo = Trabajo::withTrashed()->findOrFail($enCurso['trabajo']->id);
    $sesion = Sesion::query()->findOrFail($enCurso['sesion']->id);

    expect($estados)->toBe([
        'condiciones-1' => 'aplicado',
        'incidencia-1' => 'aplicado',
        'cierre-sesion-1' => 'aplicado',
        'cierre-trabajo-1' => 'aplicado',
    ])
        ->and($sesion->estado->value)->toBe('cerrado')
        ->and($sesion->hectareas_declaradas)->toBe('40.50')
        ->and($trabajo->estado->value)->toBe('cerrado')
        ->and($trabajo->hectareas_declaradas)->toBe('40.50')
        ->and($trabajo->litros_sobrante)->toBe('8.00')
        ->and($trabajo->imagen_campo_evidencia_id)->not->toBeNull()
        ->and($trabajo->trashed())->toBeTrue()
        ->and(Trabajo::query()->whereKey($trabajo->id)->exists())->toBeFalse()
        ->and(DB::table('ope_condiciones')->count())->toBe(1)
        ->and(DB::table('ope_incidencias')->count())->toBe(1);
});

test('las condiciones de esa sesión se deciden con los límites de su Orden de Trabajo, no con los defaults', function () {
    $enCurso = bajaTrabajoConSesionAbierta();
    app(EliminarTrabajo::class)->ejecutar($enCurso['trabajo']);

    // 15 km/h entra en el default (17) pero no en la Orden de Trabajo (12).
    $estados = bajaSync([[
        'tipo' => 'condiciones',
        'uuid_cliente' => 'condiciones-fuera',
        'sesion_uuid_cliente' => $enCurso['sesion']->uuid_cliente,
        'momento' => 'inicio_sesion',
        'viento_kmh' => '15.00',
        'temperatura_c' => '25.00',
        'humedad_pct' => '60.00',
    ]]);

    expect($estados)->toBe(['condiciones-fuera' => 'rechazado'])
        ->and(DB::table('ope_condiciones')->count())->toBe(0);
});

// ── 3. Nada nuevo sobre un trabajo dado de baja ───────────────────────────────

test('una sesión nueva después de la baja se sigue rechazando con trabajo_no_existe_aun', function () {
    $enCurso = bajaTrabajoConSesionAbierta();
    app(EliminarTrabajo::class)->ejecutar($enCurso['trabajo']);

    $resultados = app(SincronizarLote::class)->ejecutar([[
        'tipo' => 'sesion',
        'uuid_cliente' => 'sesion-nueva',
        'trabajo_uuid_cliente' => $enCurso['trabajo']->uuid_cliente,
        'secuencia' => 2,
        'piloto_id' => 5,
        'inicio' => '2026-10-02T12:00:00+00:00',
    ]], 5);

    expect($resultados[0]['estado'])->toBe('rechazado')
        ->and($resultados[0]['motivo'])->toBe(__('operaciones.sync.trabajo_no_existe_aun'))
        ->and(Sesion::query()->where('uuid_cliente', 'sesion-nueva')->exists())->toBeFalse();
});

// ── 4. Idempotencia ────────────────────────────────────────────────────────────

test('reenviar los mismos registros no duplica nada', function () {
    $enCurso = bajaTrabajoConSesionAbierta();
    app(EliminarTrabajo::class)->ejecutar($enCurso['trabajo']);
    $registros = bajaRegistrosDeCierre($enCurso);

    bajaSync($registros);
    $trabajoAntes = Trabajo::withTrashed()->findOrFail($enCurso['trabajo']->id)->only(['estado', 'cierre_uuid_cliente', 'hectareas_declaradas', 'litros_sobrante', 'imagen_campo_evidencia_id']);
    $reenvio = bajaSync($registros);

    expect($reenvio)->toBe([
        'condiciones-1' => 'duplicado',
        'incidencia-1' => 'duplicado',
        'cierre-sesion-1' => 'duplicado',
        'cierre-trabajo-1' => 'duplicado',
    ])
        ->and(Trabajo::withTrashed()->findOrFail($enCurso['trabajo']->id)->only(['estado', 'cierre_uuid_cliente', 'hectareas_declaradas', 'litros_sobrante', 'imagen_campo_evidencia_id']))->toBe($trabajoAntes)
        ->and(DB::table('ope_condiciones')->count())->toBe(1)
        ->and(DB::table('ope_incidencias')->count())->toBe(1);
});

test('un cierre de trabajo distinto sobre el trabajo ya cerrado tras la baja se rechaza como trabajo_ya_cerrado', function () {
    $enCurso = bajaTrabajoConSesionAbierta();
    app(EliminarTrabajo::class)->ejecutar($enCurso['trabajo']);
    bajaSync(bajaRegistrosDeCierre($enCurso));

    $resultados = app(SincronizarLote::class)->ejecutar([[
        'tipo' => 'cierre_trabajo',
        'uuid_cliente' => 'cierre-trabajo-otro',
        'trabajo_uuid_cliente' => $enCurso['trabajo']->uuid_cliente,
        'fin' => '2026-10-02T12:00:00+00:00',
        'evidencia_imagen_campo_uuid_cliente' => bajaEvidencia('imagen_campo'),
    ]], 5);

    expect($resultados[0]['estado'])->toBe('rechazado')
        ->and($resultados[0]['motivo'])->toBe(__('operaciones.sync.trabajo_ya_cerrado'));
});
