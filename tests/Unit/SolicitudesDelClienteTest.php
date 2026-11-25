<?php

use App\Dominios\Solicitudes\Aplicacion\CancelarSolicitud;
use App\Dominios\Solicitudes\Aplicacion\CrearSolicitud;
use App\Dominios\Solicitudes\Aplicacion\EstimarSolicitudDelCliente;
use App\Dominios\Solicitudes\Aplicacion\ListarSolicitudesDelCliente;
use App\Dominios\Solicitudes\Aplicacion\ObtenerSolicitudDelCliente;
use App\Dominios\Solicitudes\Aplicacion\PublicarSolicitud;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudAbierta;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudCancelada;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * La API del cliente sobre sus solicitudes (especificación §10.1, ADR 0027 y 0030): crear es
 * idempotente por `uuid_cliente`, todo parte del cliente de la cuenta (un id ajeno es
 * 404, invariante 5 de CLAUDE.md), abrir exige precio propuesto y teléfono, y cancelar exige
 * motivo. Contra el ESQUEMA REAL (las migraciones, en SQLite en memoria) y los casos de
 * uso reales.
 */

uses(TestCase::class);

const USUARIO_ANA = 101;
const USUARIO_BETO = 102;

function clienteEsquema(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_terreno_fotos_table',
        'create_sol_precios_referencia_table',
        'create_sol_solicitudes_servicio_table',
        'create_sol_solicitud_historial_table',
        'agrega_reputacion_a_sol_clientes',
        'agrega_negociacion_a_sol_solicitudes_servicio',
        'crea_sol_cliente_contactos',
        'create_neg_tablas_de_negociacion',
    ]);
}

/** Un cliente con su cuenta y un terreno de `$hectareas`. @return array{0: int, 1: int} cliente_id y terreno_id */
function clienteConTerreno(int $usuarioId, string $hectareas = '100.0000', bool $accesoDificil = false): array
{
    $clienteId = DB::table('sol_clientes')->insertGetId([
        'usuario_id' => $usuarioId,
        'tipo' => 'persona',
        'razon_social' => "Cliente {$usuarioId}",
        'telefono' => '70012345',
        'estado' => 'activo',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $terrenoId = DB::table('sol_terrenos')->insertGetId([
        'cliente_id' => $clienteId,
        'nombre' => "Terreno de {$usuarioId}",
        'hectareas' => $hectareas,
        'cultivo' => 'Soya',
        'acceso_dificil' => $accesoDificil,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$clienteId, $terrenoId];
}

/** @return array<string, mixed> */
function datosSolicitud(int $terrenoId, array $cambios = []): array
{
    return [
        'uuid_cliente' => (string) Str::uuid(),
        'terreno_id' => $terrenoId,
        'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '80',
        'producto_indicado' => 'Glifosato 480 SL, 2 L/ha',
        'ventana_desde' => '2026-12-05',
        'ventana_hasta' => '2026-12-15',
        'precio_ha_propuesto' => '125',
        ...$cambios,
    ];
}

beforeEach(function () {
    clienteEsquema();
    foreach (['neg_ofertas', 'sol_solicitud_historial', 'sol_solicitudes_servicio', 'sol_terreno_fotos', 'sol_precios_referencia', 'sol_terrenos', 'sol_clientes'] as $tabla) {
        DB::table($tabla)->delete();
    }
    Carbon::setTestNow('2026-12-02 10:00:00');
    // Los oyentes de la negociación (avisos a pilotos) tienen su propia prueba.
    Event::fake([SolicitudAbierta::class, SolicitudCancelada::class]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('crear una solicitud la deja en borrador con el precio propuesto, su primer paso de historial y sin monto', function () {
    [, $terreno] = clienteConTerreno(USUARIO_ANA);

    [$solicitud, $creada] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno));

    expect($creada)->toBeTrue()
        ->and($solicitud->estado)->toBe(EstadoSolicitud::Borrador)
        ->and($solicitud->hectareas_solicitadas)->toBe('80.0000')
        ->and($solicitud->cultivo)->toBe('Soya')
        ->and((string) $solicitud->precio_ha_propuesto)->toBe('125.0000')
        ->and($solicitud->monto_estimado)->toBeNull()
        ->and(DB::table('sol_solicitud_historial')->where('solicitud_id', $solicitud->id)->count())->toBe(1);
});

test('reintentar con el mismo uuid_cliente devuelve la misma solicitud y no crea otra (invariante 1)', function () {
    [, $terreno] = clienteConTerreno(USUARIO_ANA);
    $datos = datosSolicitud($terreno);

    [$primera] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, $datos);
    [$segunda, $creada] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, $datos);

    expect($creada)->toBeFalse()
        ->and($segunda->id)->toBe($primera->id)
        ->and(DB::table('sol_solicitudes_servicio')->count())->toBe(1)
        ->and(DB::table('sol_solicitud_historial')->count())->toBe(1);
});

test('el uuid_cliente de otro cliente no se reutiliza ni revela esa solicitud', function () {
    [, $terrenoAna] = clienteConTerreno(USUARIO_ANA);
    [, $terrenoBeto] = clienteConTerreno(USUARIO_BETO);
    $datos = datosSolicitud($terrenoAna);
    app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, $datos);

    expect(fn () => app(CrearSolicitud::class)->ejecutar(USUARIO_BETO, [...$datos, 'terreno_id' => $terrenoBeto]))
        ->toThrow(SolicitudInvalida::class);
    expect(DB::table('sol_solicitudes_servicio')->count())->toBe(1);
});

test('no se pide servicio sobre el terreno de otro cliente: responde como si no existiera', function () {
    clienteConTerreno(USUARIO_ANA);
    [, $terrenoBeto] = clienteConTerreno(USUARIO_BETO);

    try {
        app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terrenoBeto));
        $this->fail('Debió rechazar el terreno ajeno.');
    } catch (SolicitudInvalida $excepcion) {
        expect($excepcion->esAjeno)->toBeTrue();
    }

    expect(DB::table('sol_solicitudes_servicio')->count())->toBe(0);
});

test('las hectáreas no pueden superar las del terreno, comparadas en decimal exacto', function () {
    [, $terreno] = clienteConTerreno(USUARIO_ANA, '100.0000');

    expect(fn () => app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno, ['hectareas_solicitadas' => '100.0001'])))
        ->toThrow(SolicitudInvalida::class);

    [$justa] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno, ['hectareas_solicitadas' => '100']));

    expect($justa->hectareas_solicitadas)->toBe('100.0000');
});

test('la fecha deseada no puede estar vencida', function () {
    [, $terreno] = clienteConTerreno(USUARIO_ANA);

    expect(fn () => app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno, ['ventana_desde' => '2026-11-01', 'ventana_hasta' => '2026-11-10'])))
        ->toThrow(SolicitudInvalida::class);
});

test('un cliente suspendido no pide servicios', function () {
    [$cliente, $terreno] = clienteConTerreno(USUARIO_ANA);
    DB::table('sol_clientes')->where('id', $cliente)->update(['estado' => 'suspendido']);

    expect(fn () => app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno)))->toThrow(SolicitudInvalida::class);
});

test('un administrador cancela con su nombre como origen', function () {
    [, $terreno] = clienteConTerreno(USUARIO_ANA);
    [$solicitud] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno));

    $cancelada = app(CancelarSolicitud::class)->porAdministrador(7, $solicitud->id, 'Error de datos reconocido por soporte');

    expect($cancelada->cancelada_por->value)->toBe('administrador')
        ->and(DB::table('sol_solicitud_historial')->where('solicitud_id', $solicitud->id)->orderByDesc('id')->value('actor_usuario_id'))->toBe(7);
});

test('el cliente nunca alcanza la solicitud de otro: ver, estimar, publicar y cancelar responden como si no existiera', function () {
    [, $terrenoAna] = clienteConTerreno(USUARIO_ANA);
    clienteConTerreno(USUARIO_BETO);
    [$deAna] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terrenoAna));

    $intentos = [
        fn () => app(ObtenerSolicitudDelCliente::class)->ejecutar(USUARIO_BETO, $deAna->id),
        fn () => app(EstimarSolicitudDelCliente::class)->ejecutar(USUARIO_BETO, $deAna->id),
        fn () => app(PublicarSolicitud::class)->ejecutar(USUARIO_BETO, $deAna->id),
        fn () => app(CancelarSolicitud::class)->porCliente(USUARIO_BETO, $deAna->id, 'Nada'),
    ];

    foreach ($intentos as $intento) {
        try {
            $intento();
            $this->fail('Debió responder como si la solicitud no existiera.');
        } catch (SolicitudInvalida $excepcion) {
            expect($excepcion->esAjeno)->toBeTrue();
        }
    }

    expect($deAna->fresh()->estado)->toBe(EstadoSolicitud::Borrador);
});

test('el listado es solo del cliente y separa lo activo del historial', function () {
    [, $terrenoAna] = clienteConTerreno(USUARIO_ANA);
    [, $terrenoBeto] = clienteConTerreno(USUARIO_BETO);
    [$viva] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terrenoAna));
    [$cancelada] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terrenoAna));
    app(CancelarSolicitud::class)->porCliente(USUARIO_ANA, $cancelada->id, 'Ya no hace falta');
    app(CrearSolicitud::class)->ejecutar(USUARIO_BETO, datosSolicitud($terrenoBeto));

    $listar = app(ListarSolicitudesDelCliente::class);

    expect($listar->ejecutar(USUARIO_ANA)->total())->toBe(2)
        ->and($listar->ejecutar(USUARIO_ANA, 'activas')->pluck('id')->all())->toBe([$viva->id])
        ->and($listar->ejecutar(USUARIO_ANA, 'historial')->pluck('id')->all())->toBe([$cancelada->id])
        ->and($listar->ejecutar(USUARIO_ANA, null, EstadoSolicitud::Borrador)->pluck('id')->all())->toBe([$viva->id]);
});

test('abrir congela el monto estimado con el precio propuesto, fija la vigencia y anuncia el hecho', function () {
    Event::fake([SolicitudAbierta::class, SolicitudCancelada::class]);
    [, $terreno] = clienteConTerreno(USUARIO_ANA, '100');
    [$solicitud] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno));

    $abierta = app(PublicarSolicitud::class)->ejecutar(USUARIO_ANA, $solicitud->id);

    // 80 ha × 125,00 = 10.000,00; sin recargos: el precio lo pone la negociación.
    expect($abierta->estado)->toBe(EstadoSolicitud::Abierta)
        ->and($abierta->monto_estimado)->toBe('10000.00')
        ->and($abierta->publicada_en)->not->toBeNull()
        ->and($abierta->vence_en->toDateTimeString())->toBe('2026-12-09 10:00:00');
    Event::assertDispatched(SolicitudAbierta::class, fn (SolicitudAbierta $e): bool => $e->solicitudId === $solicitud->id);
});

test('abrir exige precio propuesto y teléfono en la cuenta', function () {
    [$cliente, $terreno] = clienteConTerreno(USUARIO_ANA);
    [$sinPrecio] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno, ['precio_ha_propuesto' => null]));

    expect(fn () => app(PublicarSolicitud::class)->ejecutar(USUARIO_ANA, $sinPrecio->id))->toThrow(SolicitudInvalida::class);

    [$conPrecio] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno));
    DB::table('sol_clientes')->where('id', $cliente)->update(['telefono' => null]);

    expect(fn () => app(PublicarSolicitud::class)->ejecutar(USUARIO_ANA, $conPrecio->id))->toThrow(SolicitudInvalida::class);
});

test('la estimación es el monto con el precio propuesto', function () {
    [, $terreno] = clienteConTerreno(USUARIO_ANA);
    [$solicitud] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno));

    $estimacion = app(EstimarSolicitudDelCliente::class)->ejecutar(USUARIO_ANA, $solicitud->id);

    expect($estimacion['precio_acordado'])->toBeFalse()
        ->and($estimacion['monto_estimado'])->toBe('10000.00');
});

test('una abierta no se vuelve a abrir', function () {
    [, $terreno] = clienteConTerreno(USUARIO_ANA);
    [$solicitud] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno));
    app(PublicarSolicitud::class)->ejecutar(USUARIO_ANA, $solicitud->id);

    expect(fn () => app(PublicarSolicitud::class)->ejecutar(USUARIO_ANA, $solicitud->id))
        ->toThrow(TransicionSolicitudNoPermitida::class);
});

test('una expirada se renueva dentro del plazo y no después, sin cambiar el precio propuesto', function () {
    [, $terreno] = clienteConTerreno(USUARIO_ANA);
    [$solicitud] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno));
    app(PublicarSolicitud::class)->ejecutar(USUARIO_ANA, $solicitud->id);
    DB::table('sol_solicitudes_servicio')->where('id', $solicitud->id)->update(['estado' => 'expirada', 'expirada_en' => now()->subDays(3)]);

    $renovada = app(PublicarSolicitud::class)->ejecutar(USUARIO_ANA, $solicitud->id);

    expect($renovada->estado)->toBe(EstadoSolicitud::Abierta)
        ->and((string) $renovada->precio_ha_propuesto)->toBe('125.0000');

    DB::table('sol_solicitudes_servicio')->where('id', $solicitud->id)->update(['estado' => 'expirada', 'expirada_en' => now()->subDays(31)]);

    expect(fn () => app(PublicarSolicitud::class)->ejecutar(USUARIO_ANA, $solicitud->id))->toThrow(SolicitudInvalida::class);
});

test('cancelar exige motivo, guarda quién y por qué, y anuncia el hecho', function () {
    Event::fake([SolicitudAbierta::class, SolicitudCancelada::class]);
    [, $terreno] = clienteConTerreno(USUARIO_ANA);
    [$solicitud] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno));
    app(PublicarSolicitud::class)->ejecutar(USUARIO_ANA, $solicitud->id);

    expect(fn () => app(CancelarSolicitud::class)->porCliente(USUARIO_ANA, $solicitud->id, '   '))->toThrow(SolicitudInvalida::class);

    $cancelada = app(CancelarSolicitud::class)->porCliente(USUARIO_ANA, $solicitud->id, 'Cambió la fecha de siembra');

    expect($cancelada->estado)->toBe(EstadoSolicitud::Cancelada)
        ->and($cancelada->cancelada_por->value)->toBe('cliente')
        ->and($cancelada->motivo_cancelacion)->toBe('Cambió la fecha de siembra');
    Event::assertDispatched(SolicitudCancelada::class, fn (SolicitudCancelada $e): bool => $e->estadoPrevio === 'abierta' && $e->canceladaPor === 'cliente');
});

test('una solicitud en ejecución no se cancela por el camino del cliente', function () {
    [, $terreno] = clienteConTerreno(USUARIO_ANA);
    [$solicitud] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno));
    DB::table('sol_solicitudes_servicio')->where('id', $solicitud->id)->update(['estado' => 'en_ejecucion']);

    expect(fn () => app(CancelarSolicitud::class)->porCliente(USUARIO_ANA, $solicitud->id, 'Ya no la quiero'))
        ->toThrow(TransicionSolicitudNoPermitida::class);
});

test('el detalle trae el historial de cada cambio de estado en orden', function () {
    [, $terreno] = clienteConTerreno(USUARIO_ANA);
    [$solicitud] = app(CrearSolicitud::class)->ejecutar(USUARIO_ANA, datosSolicitud($terreno));
    app(PublicarSolicitud::class)->ejecutar(USUARIO_ANA, $solicitud->id);
    app(CancelarSolicitud::class)->porCliente(USUARIO_ANA, $solicitud->id, 'Cambio de planes');

    $detalle = app(ObtenerSolicitudDelCliente::class)->ejecutar(USUARIO_ANA, $solicitud->id);

    expect($detalle->historial->map(fn ($paso): string => ($paso->estado_anterior?->value ?? 'nada').'>'.$paso->estado_nuevo->value)->all())
        ->toBe(['nada>borrador', 'borrador>abierta', 'abierta>cancelada']);
});
