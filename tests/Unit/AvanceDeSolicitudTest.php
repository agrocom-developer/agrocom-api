<?php

use App\Dominios\Solicitudes\Aplicacion\AvanceDeSolicitud;
use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * El trabajo del lote y la máquina de estados de la solicitud (invariante 7 de CLAUDE.md;
 * `negociacion-contrato.md` §4, «Compatibilidad»): el primer trabajo del equipo inicia la
 * ejecución de una solicitud adjudicada; lo cubierto por los trabajos NO la finaliza (el cierre es el
 * registro `cierre` de la negociación). Cada paso deja su fila de historial. Contra el ESQUEMA REAL
 * (las migraciones, en SQLite en memoria).
 */

uses(TestCase::class);

function avanceEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_sol_clientes_table',
        'create_sol_solicitudes_servicio_table',
        'create_sol_solicitud_historial_table',
        'agrega_negociacion_a_sol_solicitudes_servicio',
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
}

function avanceSolicitud(EstadoSolicitud $estado, string $hectareas = '100.0000'): SolicitudServicio
{
    DB::table('sol_clientes')->insertOrIgnore([
        'id' => 1,
        'usuario_id' => 55,
        'tipo' => 'persona',
        'razon_social' => 'Cliente de prueba',
        'estado' => 'aprobado',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $id = DB::table('sol_solicitudes_servicio')->insertGetId([
        'uuid_cliente' => (string) Str::uuid(),
        'cliente_id' => 1,
        'terreno_id' => 1,
        'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => $hectareas,
        'producto_indicado' => 'Glifosato',
        'aporta_producto' => 'cliente',
        'ventana_desde' => '2026-12-01',
        'ventana_hasta' => '2026-12-10',
        'urgente' => false,
        'estado' => $estado->value,
        'equipo_id' => 7,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return SolicitudServicio::query()->findOrFail($id);
}

/** @return list<string> los estados destino del historial, en orden */
function avanceHistorial(int $solicitudId): array
{
    return DB::table('sol_solicitud_historial')->where('solicitud_id', $solicitudId)->orderBy('id')->pluck('estado_nuevo')->all();
}

beforeEach(function () {
    avanceEsquema();
    DB::table('sol_solicitudes_servicio')->delete();
    DB::table('sol_solicitud_historial')->delete();
});

test('el primer trabajo pasa una solicitud adjudicada a en ejecución, con su historial', function () {
    $solicitud = avanceSolicitud(EstadoSolicitud::Adjudicada);

    app(AvanceDeSolicitud::class)->iniciarEjecucion($solicitud->id);

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::EnEjecucion)
        ->and($solicitud->fresh()->iniciada_en)->not->toBeNull()
        ->and(avanceHistorial($solicitud->id))->toBe(['en_ejecucion']);
});

test('iniciar la ejecución es idempotente y no toca una solicitud que no corresponde', function () {
    $adjudicada = avanceSolicitud(EstadoSolicitud::Adjudicada);
    $abierta = avanceSolicitud(EstadoSolicitud::Abierta);
    $finalizada = avanceSolicitud(EstadoSolicitud::Finalizada);

    foreach ([1, 2, 3] as $vez) {
        app(AvanceDeSolicitud::class)->iniciarEjecucion($adjudicada->id);
        app(AvanceDeSolicitud::class)->iniciarEjecucion($abierta->id);
        app(AvanceDeSolicitud::class)->iniciarEjecucion($finalizada->id);
    }
    app(AvanceDeSolicitud::class)->iniciarEjecucion(999999);

    expect($adjudicada->fresh()->estado)->toBe(EstadoSolicitud::EnEjecucion)
        ->and(avanceHistorial($adjudicada->id))->toBe(['en_ejecucion'])
        ->and($abierta->fresh()->estado)->toBe(EstadoSolicitud::Abierta)
        ->and($finalizada->fresh()->estado)->toBe(EstadoSolicitud::Finalizada)
        ->and(DB::table('sol_solicitud_historial')->count())->toBe(1);
});

test('lo cubierto por los trabajos cerrados no finaliza la solicitud', function () {
    $solicitud = avanceSolicitud(EstadoSolicitud::EnEjecucion);

    app(AvanceDeSolicitud::class)->registrarCobertura($solicitud->id, '100.0000');

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::EnEjecucion)->and(DB::table('sol_solicitud_historial')->count())->toBe(0);
});

test('la app de campo recibe el servicio solo desde la adjudicación', function () {
    $adjudicada = avanceSolicitud(EstadoSolicitud::Adjudicada);
    $abierta = avanceSolicitud(EstadoSolicitud::Abierta);

    expect(app(AvanceDeSolicitud::class)->datos($adjudicada->id)->admiteTrabajo)->toBeTrue()
        ->and(app(AvanceDeSolicitud::class)->datos($abierta->id)->admiteTrabajo)->toBeFalse()
        ->and(app(AvanceDeSolicitud::class)->datos(999999))->toBeNull();
});

test('la máquina de estados rechaza una transición que la tabla no permite y no escribe nada', function () {
    $solicitud = avanceSolicitud(EstadoSolicitud::Calificada);

    expect(fn () => app(MaquinaEstadosSolicitud::class)->transicionar($solicitud, EstadoSolicitud::Abierta, ActorSolicitud::Administrador, 1))
        ->toThrow(TransicionSolicitudNoPermitida::class)
        ->and($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Calificada)
        ->and(DB::table('sol_solicitud_historial')->count())->toBe(0);
});

test('cancelar guarda quién, por qué y deja el historial con el motivo', function () {
    $solicitud = avanceSolicitud(EstadoSolicitud::Adjudicada);

    app(MaquinaEstadosSolicitud::class)->transicionar(
        $solicitud,
        EstadoSolicitud::Cancelada,
        ActorSolicitud::Cliente,
        1,
        'Cambió la fecha de siembra',
        ['motivo_cancelacion' => 'Cambió la fecha de siembra', 'cancelada_por' => ActorSolicitud::Cliente],
    );

    $fila = DB::table('sol_solicitudes_servicio')->where('id', $solicitud->id)->first();
    $paso = DB::table('sol_solicitud_historial')->where('solicitud_id', $solicitud->id)->first();

    expect($fila->estado)->toBe('cancelada')
        ->and($fila->cancelada_por)->toBe('cliente')
        ->and($fila->motivo_cancelacion)->toBe('Cambió la fecha de siembra')
        ->and($paso->estado_anterior)->toBe('adjudicada')
        ->and($paso->origen)->toBe('cliente')
        ->and($paso->motivo)->toBe('Cambió la fecha de siembra');
});
