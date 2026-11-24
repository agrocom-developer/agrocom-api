<?php

use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\LecturaLimitesDeVuelo;
use App\Dominios\Operaciones\Contratos\RegistroCondiciones;
use App\Dominios\Operaciones\Dominio\LimitesEfectivos;
use App\Dominios\Solicitudes\Contratos\SolicitudParaTrabajo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * Registro `condiciones` de `POST /api/sync` (espec §5 y §9.2, «condiciones dentro
 * de rango») contra los límites vigentes del sistema —los mismos que la app recibe
 * en `limites` del catálogo—. Contra el ESQUEMA REAL (las migraciones, en SQLite en
 * memoria) y la implementación real de `EscrituraSincronizacion::registrarCondiciones()`.
 *
 * Lo que se garantiza:
 *
 *   1. Se aplican las constantes del sistema (viento 17, temperatura 30, humedad 90).
 *   2. Un valor fuera de rango sin observación firmada se rechaza y no persiste nada.
 *   3. La comparación es DECIMAL exacta.
 *   4. El catálogo entrega los mismos límites con los que el servidor valida.
 */

uses(TestCase::class);

function condicionesEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_ope_trabajos_table',
        'vincula_trabajos_a_solicitudes',
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

/** Sesión abierta sobre un trabajo de la solicitud 31. Devuelve su `uuid_cliente`. */
function condicionesSesion(): string
{
    $trabajoId = DB::table('ope_trabajos')->insertGetId([
        'uuid_cliente' => (string) Str::uuid(),
        'solicitud_id' => 31,
        'estado' => 'abierto',
        'inicio' => now(),
        'equipo_trabajo_id' => 7,
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
    $this->mock(SolicitudParaTrabajo::class)->shouldIgnoreMissing();
});

test('se aplican las constantes del sistema: en el límite pasa, apenas por encima se rechaza', function () {
    $sesion = condicionesSesion();

    expect(condicionesRegistrar($sesion, '17.00', '30.00', '90.00'))->toBe('aplicado')
        ->and(condicionesRegistrar($sesion, '17.01', '20.00', '60.00'))->toBe('rechazado')
        ->and(condicionesRegistrar($sesion, '10.00', '30.01', '60.00'))->toBe('rechazado')
        ->and(condicionesRegistrar($sesion, '10.00', '20.00', '90.01'))->toBe('rechazado');
});

test('la humedad mínima no se exige: el sistema no fija un piso', function () {
    $sesion = condicionesSesion();

    expect(condicionesRegistrar($sesion, '10.00', '20.00', '10.00'))->toBe('aplicado');
});

test('un rechazo por límite no persiste la fila de condiciones', function () {
    $sesion = condicionesSesion();

    condicionesRegistrar($sesion, '18.00', '20.00', '60.00');

    expect(DB::table('ope_condiciones')->count())->toBe(0);
});

test('la comparación es DECIMAL exacta y tolera los espacios que admite is_numeric', function () {
    $limites = LimitesEfectivos::resolver(null, null, '12.10', null, null, null, null);

    expect($limites->admiteCondiciones('12.1', '20', '60'))->toBeTrue()
        ->and($limites->admiteCondiciones('12.11', '20', '60'))->toBeFalse()
        ->and($limites->admiteCondiciones(' 12 ', ' 20', '60 '))->toBeTrue();
});

test('el catálogo entrega los mismos límites con los que el servidor valida', function () {
    $limites = app(LecturaLimitesDeVuelo::class)->efectivos();

    expect($limites)->toBe([
        'humedad_min_pct' => null,
        'viento_max_kmh' => '17.00',
        'temperatura_max_c' => '30.00',
        'humedad_max_pct' => '90.00',
        'altura_vuelo_m' => null,
        'velocidad_vuelo_kmh' => null,
        'ancho_pasada_m' => null,
    ]);
});
