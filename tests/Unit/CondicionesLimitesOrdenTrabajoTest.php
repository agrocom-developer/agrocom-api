<?php

use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\RegistroCondiciones;
use App\Dominios\Operaciones\Dominio\LimitesEfectivos;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * Registro `condiciones` de `POST /api/sync` (espec §5, "condiciones dentro de
 * rango") contra los límites EFECTIVOS del trabajo de la sesión — decisión del
 * dueño del 1/10/2026: los mismos que la app recibe en `trabajos[]` del
 * catálogo. Contra el ESQUEMA REAL (las migraciones, en SQLite en memoria) y la
 * implementación real de `EscrituraSincronizacion::registrarCondiciones()`.
 *
 * Lo que se garantiza:
 *
 *   1. Sin límite en la Orden de Trabajo (o sin Orden de Trabajo), se aplica la
 *      constante de `RegistroCondiciones` (viento 17, temperatura 30, humedad 90).
 *   2. Con límite propio, manda el de la Orden de Trabajo, sea más permisivo o
 *      más estricto que la constante.
 *   3. Fuera de rango sin observación firmada, se rechaza y no persiste nada.
 */

uses(TestCase::class);

function condicionesEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_ope_ordenes_trabajo_table',
        'create_ope_trabajos_table',
        'create_ope_sesiones_table',
        'create_ope_condiciones_table',
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

/**
 * Sesión abierta sobre un trabajo cuya Orden de Trabajo trae `$limites`
 * (`null`: el trabajo no tiene Orden de Trabajo). Devuelve su `uuid_cliente`.
 *
 * @param  array<string, string>|null  $limites
 */
function condicionesSesion(?array $limites): string
{
    $ordenTrabajoId = $limites === null ? null : DB::table('ope_ordenes_trabajo')->insertGetId([
        'orden_id' => 1,
        'nro_aplicacion' => 1,
        'created_at' => now(),
        'updated_at' => now(),
        ...$limites,
    ]);

    $trabajoId = DB::table('ope_trabajos')->insertGetId([
        'uuid_cliente' => (string) Str::uuid(),
        'orden_id' => 1,
        'lote_id' => 3,
        'nro_aplicacion' => 1,
        'estado' => 'abierto',
        'inicio' => now(),
        'equipo_trabajo_id' => 7,
        'orden_trabajo_id' => $ordenTrabajoId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $uuid = (string) Str::uuid();

    DB::table('ope_sesiones')->insert([
        'uuid_cliente' => $uuid,
        'trabajo_id' => $trabajoId,
        'secuencia' => 1,
        'piloto_id' => 5,
        'estado' => 'abierto',
        'inicio' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $uuid;
}

/** Registra condiciones sin observación del agrónomo: `aplicado` solo si caen dentro de rango. */
function condicionesRegistrar(string $sesionUuid, string $viento, string $temperatura, string $humedad): string
{
    $datos = RegistroCondiciones::intentarDesdeArreglo([
        'uuid_cliente' => (string) Str::uuid(),
        'sesion_uuid_cliente' => $sesionUuid,
        'momento' => 'inicio_sesion',
        'viento_kmh' => $viento,
        'temperatura_c' => $temperatura,
        'humedad_pct' => $humedad,
    ]);

    expect($datos)->not->toBeNull();

    return app(EscrituraSincronizacion::class)->registrarCondiciones($datos)->estado;
}

beforeEach(function () {
    condicionesEsquema();
});

// ── 1. Sin límite propio: la constante ─────────────────────────────────────────

test('sin límites en la Orden de Trabajo se aplican las constantes del sistema', function () {
    $sesion = condicionesSesion(['ph_agua' => '7.00']);

    expect(condicionesRegistrar($sesion, '17.00', '30.00', '90.00'))->toBe('aplicado')
        ->and(condicionesRegistrar($sesion, '17.01', '20.00', '60.00'))->toBe('rechazado')
        ->and(condicionesRegistrar($sesion, '10.00', '30.01', '60.00'))->toBe('rechazado')
        ->and(condicionesRegistrar($sesion, '10.00', '20.00', '90.01'))->toBe('rechazado');
});

test('un trabajo sin Orden de Trabajo también usa las constantes', function () {
    $sesion = condicionesSesion(null);

    expect(condicionesRegistrar($sesion, '17.00', '30.00', '90.00'))->toBe('aplicado')
        ->and(condicionesRegistrar($sesion, '18.00', '20.00', '60.00'))->toBe('rechazado');
});

// ── 2. Con límite propio: manda la Orden de Trabajo ────────────────────────────

test('un límite propio más permisivo que la constante autoriza lo que la constante rechazaría', function () {
    $sesion = condicionesSesion([
        'viento_max_kmh' => '22.00',
        'temperatura_max_c' => '35.00',
        'humedad_max_pct' => '95.00',
    ]);

    expect(condicionesRegistrar($sesion, '21.00', '34.00', '94.00'))->toBe('aplicado')
        ->and(condicionesRegistrar($sesion, '22.50', '20.00', '60.00'))->toBe('rechazado');
});

test('un límite propio más estricto que la constante rechaza lo que la constante autorizaría', function () {
    $sesion = condicionesSesion([
        'viento_max_kmh' => '12.00',
        'temperatura_max_c' => '26.00',
        'humedad_max_pct' => '80.00',
    ]);

    expect(condicionesRegistrar($sesion, '12.00', '26.00', '80.00'))->toBe('aplicado')
        ->and(condicionesRegistrar($sesion, '15.00', '20.00', '60.00'))->toBe('rechazado')
        ->and(condicionesRegistrar($sesion, '10.00', '28.00', '60.00'))->toBe('rechazado')
        ->and(condicionesRegistrar($sesion, '10.00', '20.00', '85.00'))->toBe('rechazado');
});

test('cada límite en blanco hereda su constante aunque otro sea propio', function () {
    $sesion = condicionesSesion(['viento_max_kmh' => '12.00']);

    expect(condicionesRegistrar($sesion, '11.00', '30.00', '90.00'))->toBe('aplicado')
        ->and(condicionesRegistrar($sesion, '11.00', '30.50', '60.00'))->toBe('rechazado')
        ->and(condicionesRegistrar($sesion, '13.00', '20.00', '60.00'))->toBe('rechazado');
});

test('la humedad mínima solo se exige si la Orden de Trabajo la fija', function () {
    $conMinima = condicionesSesion(['humedad_min_pct' => '55.00']);
    $sinMinima = condicionesSesion(['ph_agua' => '7.00']);

    expect(condicionesRegistrar($conMinima, '10.00', '20.00', '55.00'))->toBe('aplicado')
        ->and(condicionesRegistrar($conMinima, '10.00', '20.00', '50.00'))->toBe('rechazado')
        ->and(condicionesRegistrar($sinMinima, '10.00', '20.00', '10.00'))->toBe('aplicado');
});

// ── 3. Fuera de rango sin observación: nada persiste ──────────────────────────

test('un rechazo por límite propio no persiste la fila de condiciones', function () {
    $sesion = condicionesSesion(['viento_max_kmh' => '12.00']);

    condicionesRegistrar($sesion, '15.00', '20.00', '60.00');

    expect(DB::table('ope_condiciones')->count())->toBe(0);
});

test('la comparación es DECIMAL exacta y tolera los espacios que admite is_numeric', function () {
    $limites = LimitesEfectivos::resolver(null, null, '12.10', null, null, null, null);

    expect($limites->admiteCondiciones('12.1', '20', '60'))->toBeTrue()
        ->and($limites->admiteCondiciones('12.11', '20', '60'))->toBeFalse()
        ->and($limites->admiteCondiciones(' 12 ', ' 20', '60 '))->toBeTrue();
});
