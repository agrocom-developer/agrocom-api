<?php

use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Sincronizacion\Aplicacion\SincronizarLote;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudParaTrabajo;
use App\Dominios\Solicitudes\Contratos\SolicitudParaTrabajo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * El trabajo nace de una solicitud aceptada (ADR 0037) y el motor de sync mantiene
 * sus garantías (invariante 1 de CLAUDE.md): el `trabajo` solo se abre sobre una
 * solicitud con equipo, reenviarlo es `duplicado` aunque la solicitud ya haya
 * avanzado, y cerrar el trabajo le informa a la solicitud lo cubierto. Por el motor
 * real (`SincronizarLote` → `EscrituraSincronizacionEloquent`), contra el ESQUEMA
 * REAL (las migraciones, en SQLite en memoria). `Solicitudes` se reemplaza por un
 * doble que registra lo que el sync le pide.
 */

uses(TestCase::class);

function trabajoSolicitudEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_ope_trabajos_table',
        'vincula_trabajos_a_solicitudes',
        'create_ope_sesiones_table',
        'create_ope_evidencias_table',
    ];

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
            if (! str_contains($excepcion->getMessage(), 'USING btree') && ! str_contains($excepcion->getMessage(), 'already exists')) {
                throw $excepcion;
            }
        }
    }

    // El índice único parcial por `uuid_cliente` lo crea la migración con `USING btree`
    // (solo Postgres): se recrea sin esa cláusula para probar el reenvío contra la garantía real.
    DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS ope_trabajos_uuid_cliente_unico ON ope_trabajos (uuid_cliente) WHERE deleted_at IS NULL');
}

/**
 * Doble de `Solicitudes`: la solicitud 31 admite trabajo según `$admite` y todo lo que
 * el sync le pide queda registrado.
 */
function trabajoSolicitudDoble(bool $admite = true): object
{
    return new class($admite) implements SolicitudParaTrabajo
    {
        /** @var list<int> */
        public array $iniciadas = [];

        /** @var list<array{int, string}> */
        public array $coberturas = [];

        public function __construct(public bool $admite) {}

        public function datos(int $solicitudId): ?DatosSolicitudParaTrabajo
        {
            return $solicitudId === 31
                ? new DatosSolicitudParaTrabajo(31, $this->admite ? 'asignada' : 'completada', $this->admite, 7, '80.0000')
                : null;
        }

        public function iniciarEjecucion(int $solicitudId): void
        {
            $this->iniciadas[] = $solicitudId;
        }

        public function registrarCobertura(int $solicitudId, string $hectareasCerradas): void
        {
            $this->coberturas[] = [$solicitudId, $hectareasCerradas];
        }
    };
}

function trabajoSolicitudRegistro(string $uuid, int $solicitudId = 31): array
{
    return [
        'tipo' => 'trabajo',
        'uuid_cliente' => $uuid,
        'solicitud_id' => $solicitudId,
        'hectareas_declaradas' => '0',
        'inicio' => '2026-12-02T10:00:00+00:00',
    ];
}

beforeEach(function () {
    trabajoSolicitudEsquema();
    DB::table('ope_trabajos')->delete();
    Carbon::setTestNow('2026-12-02 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('el trabajo se abre sobre una solicitud con equipo y la pasa a ejecución', function () {
    $doble = trabajoSolicitudDoble();
    $this->app->instance(SolicitudParaTrabajo::class, $doble);
    $uuid = (string) Str::uuid();

    $resultados = app(SincronizarLote::class)->ejecutar([trabajoSolicitudRegistro($uuid)], 5);

    expect($resultados[0]['estado'])->toBe('aplicado')
        ->and(Trabajo::query()->where('uuid_cliente', $uuid)->value('solicitud_id'))->toBe(31)
        ->and($doble->iniciadas)->toBe([31]);
});

test('un trabajo sobre una solicitud sin equipo o ya terminada se rechaza y no crea nada', function () {
    $this->app->instance(SolicitudParaTrabajo::class, trabajoSolicitudDoble(admite: false));

    $terminada = app(SincronizarLote::class)->ejecutar([trabajoSolicitudRegistro((string) Str::uuid())], 5);
    $inexistente = app(SincronizarLote::class)->ejecutar([trabajoSolicitudRegistro((string) Str::uuid(), 999)], 5);

    expect($terminada[0]['estado'])->toBe('rechazado')
        ->and($terminada[0]['motivo'])->toBe(__('operaciones.sync.solicitud_no_vigente'))
        ->and($inexistente[0]['estado'])->toBe('rechazado')
        ->and(Trabajo::query()->count())->toBe(0);
});

test('un registro de trabajo sin solicitud se rechaza sin frenar el resto del lote', function () {
    $this->app->instance(SolicitudParaTrabajo::class, trabajoSolicitudDoble());

    $resultados = app(SincronizarLote::class)->ejecutar([
        ['tipo' => 'trabajo', 'uuid_cliente' => (string) Str::uuid(), 'inicio' => '2026-12-02T10:00:00+00:00'],
        trabajoSolicitudRegistro((string) Str::uuid()),
    ], 5);

    expect(array_column($resultados, 'estado'))->toBe(['rechazado', 'aplicado']);
});

test('reenviar el mismo trabajo es duplicado aunque la solicitud ya no admita trabajo', function () {
    $doble = trabajoSolicitudDoble();
    $this->app->instance(SolicitudParaTrabajo::class, $doble);
    $uuid = (string) Str::uuid();
    $registro = trabajoSolicitudRegistro($uuid);

    app(SincronizarLote::class)->ejecutar([$registro], 5);

    // La solicitud avanzó (ya ejecutada): el reenvío del mismo registro no es un error.
    $doble->admite = false;
    $reenvio = app(SincronizarLote::class)->ejecutar([$registro, $registro], 5);

    expect(array_column($reenvio, 'estado'))->toBe(['duplicado', 'duplicado'])
        ->and(Trabajo::query()->where('uuid_cliente', $uuid)->count())->toBe(1)
        ->and($doble->iniciadas)->toBe([31]);
});

test('al cerrar el trabajo la solicitud recibe lo declarado por los trabajos cerrados', function () {
    $doble = trabajoSolicitudDoble();
    $this->app->instance(SolicitudParaTrabajo::class, $doble);
    $uuid = (string) Str::uuid();
    $imagen = (string) Str::uuid();

    DB::table('ope_evidencias')->insert([
        'uuid_cliente' => $imagen,
        'tipo' => 'imagen_campo',
        'archivo_url' => "evidencias/{$imagen}.jpg",
        'hash' => str_repeat('a', 64),
        'fecha' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(SincronizarLote::class)->ejecutar([trabajoSolicitudRegistro($uuid)], 5);
    DB::table('ope_sesiones')->insert([
        'uuid_cliente' => (string) Str::uuid(),
        'trabajo_id' => Trabajo::query()->where('uuid_cliente', $uuid)->value('id'),
        'secuencia' => 1,
        'piloto_id' => 5,
        'estado' => 'cerrado',
        'inicio' => now(),
        'hectareas_declaradas' => '78.2500',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $resultados = app(SincronizarLote::class)->ejecutar([[
        'tipo' => 'cierre_trabajo',
        'uuid_cliente' => 'cierre-1',
        'trabajo_uuid_cliente' => $uuid,
        'fin' => '2026-12-02T12:00:00+00:00',
        'evidencia_imagen_campo_uuid_cliente' => $imagen,
    ]], 5);

    expect($resultados[0]['estado'])->toBe('aplicado')
        ->and($doble->coberturas)->toHaveCount(1)
        ->and($doble->coberturas[0][0])->toBe(31)
        ->and(bccomp($doble->coberturas[0][1], '78.25', 2))->toBe(0);
});
