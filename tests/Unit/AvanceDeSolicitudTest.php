<?php

use App\Dominios\Solicitudes\Aplicacion\AvanceDeSolicitud;
use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudEjecutada;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * La ejecución en el lote hace avanzar la solicitud por su máquina de estados
 * (invariante 7 de CLAUDE.md; ADR 0037): primer trabajo → `en_ejecucion`, recorriendo
 * `asignada → en_camino` si hace falta; lo cubierto por los trabajos cerrados →
 * `ejecutada`. Cada paso deja su fila de historial. Contra el ESQUEMA REAL (las
 * migraciones, en SQLite en memoria).
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
        'estado' => 'activo',
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

test('el primer trabajo pasa una solicitud asignada por en camino hasta en ejecución, con su historial', function () {
    $solicitud = avanceSolicitud(EstadoSolicitud::Asignada);

    app(AvanceDeSolicitud::class)->iniciarEjecucion($solicitud->id);

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::EnEjecucion)
        ->and(avanceHistorial($solicitud->id))->toBe(['en_camino', 'en_ejecucion'])
        ->and(DB::table('sol_solicitud_historial')->where('solicitud_id', $solicitud->id)->pluck('origen')->unique()->all())->toBe(['sistema']);
});

test('una solicitud que ya salió en camino solo da el último paso', function () {
    $solicitud = avanceSolicitud(EstadoSolicitud::EnCamino);

    app(AvanceDeSolicitud::class)->iniciarEjecucion($solicitud->id);

    expect(avanceHistorial($solicitud->id))->toBe(['en_ejecucion']);
});

test('una suspendida por clima se reprograma, sale y entra a ejecución', function () {
    $solicitud = avanceSolicitud(EstadoSolicitud::SuspendidaPorClima);

    app(AvanceDeSolicitud::class)->iniciarEjecucion($solicitud->id);

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::EnEjecucion)
        ->and(avanceHistorial($solicitud->id))->toBe(['asignada', 'en_camino', 'en_ejecucion']);
});

test('iniciar la ejecución es idempotente y no toca una solicitud que no corresponde', function () {
    $enEjecucion = avanceSolicitud(EstadoSolicitud::EnEjecucion);
    $cancelada = avanceSolicitud(EstadoSolicitud::Cancelada);

    app(AvanceDeSolicitud::class)->iniciarEjecucion($enEjecucion->id);
    app(AvanceDeSolicitud::class)->iniciarEjecucion($enEjecucion->id);
    app(AvanceDeSolicitud::class)->iniciarEjecucion($cancelada->id);

    expect(avanceHistorial($enEjecucion->id))->toBe([])
        ->and($cancelada->fresh()->estado)->toBe(EstadoSolicitud::Cancelada)
        ->and(avanceHistorial($cancelada->id))->toBe([]);
});

test('lo cubierto pasa la solicitud a ejecutada solo desde el porcentaje mínimo, y lo anuncia', function () {
    Event::fake([SolicitudEjecutada::class]);
    config(['solicitudes.cobertura_minima_pct' => '95']);
    $solicitud = avanceSolicitud(EstadoSolicitud::EnEjecucion, '100.0000');

    app(AvanceDeSolicitud::class)->registrarCobertura($solicitud->id, '94.9999');

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::EnEjecucion);
    Event::assertNotDispatched(SolicitudEjecutada::class);

    app(AvanceDeSolicitud::class)->registrarCobertura($solicitud->id, '95.0000');

    expect($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Ejecutada)
        ->and(avanceHistorial($solicitud->id))->toBe(['ejecutada']);
    Event::assertDispatched(SolicitudEjecutada::class, fn (SolicitudEjecutada $evento): bool => $evento->solicitudId === $solicitud->id && $evento->clienteUsuarioId === 55);
});

test('la cobertura de una solicitud que ya no está en ejecución no la cambia', function () {
    $cancelada = avanceSolicitud(EstadoSolicitud::Cancelada);
    $ejecutada = avanceSolicitud(EstadoSolicitud::Ejecutada);

    app(AvanceDeSolicitud::class)->registrarCobertura($cancelada->id, '100.0000');
    app(AvanceDeSolicitud::class)->registrarCobertura($ejecutada->id, '100.0000');

    expect($cancelada->fresh()->estado)->toBe(EstadoSolicitud::Cancelada)
        ->and($ejecutada->fresh()->estado)->toBe(EstadoSolicitud::Ejecutada)
        ->and(DB::table('sol_solicitud_historial')->count())->toBe(0);
});

test('la máquina de estados rechaza una transición que la tabla no permite y no escribe nada', function () {
    $solicitud = avanceSolicitud(EstadoSolicitud::Completada);

    expect(fn () => app(MaquinaEstadosSolicitud::class)->transicionar($solicitud, EstadoSolicitud::Publicada, ActorSolicitud::Administrador, 1))
        ->toThrow(TransicionSolicitudNoPermitida::class)
        ->and($solicitud->fresh()->estado)->toBe(EstadoSolicitud::Completada)
        ->and(DB::table('sol_solicitud_historial')->count())->toBe(0);
});

test('cancelar guarda quién, por qué y deja el historial con el motivo', function () {
    $solicitud = avanceSolicitud(EstadoSolicitud::Asignada);

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
        ->and($paso->estado_anterior)->toBe('asignada')
        ->and($paso->origen)->toBe('cliente')
        ->and($paso->motivo)->toBe('Cambió la fecha de siembra');
});
