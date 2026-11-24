<?php

use App\Dominios\Flota\Contratos\DatosIntegrantesEquipo;
use App\Dominios\Flota\Contratos\LecturaIntegrantesDeEquipo;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Aplicacion\CompletarSolicitudesVencidas;
use App\Dominios\Solicitudes\Aplicacion\ConformarSolicitud;
use App\Dominios\Solicitudes\Aplicacion\PublicarSolicitud;
use App\Dominios\Solicitudes\Aplicacion\ReputacionDeClientePorSolicitudes;
use App\Dominios\Solicitudes\Aplicacion\ResolucionDeServicioPorMaquina;
use App\Dominios\Solicitudes\Contratos\Eventos\ClienteAvisadoPorCalificacion;
use App\Dominios\Solicitudes\Contratos\Eventos\PublicacionDeClienteLimitada;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudCancelada;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudCompletada;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use App\Dominios\Solicitudes\Dominio\ComisionDeServicio;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Dominio\MontoDelServicio;
use App\Dominios\Solicitudes\Dominio\PoliticaDeCliente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Soporte\Esquema;
use Tests\Soporte\ProveedoresFalsos;
use Tests\TestCase;

/*
 * El cierre del servicio (especificación §5.1, política de comisión y de
 * calificación, ADR 0034 y 0039): conformidad explícita y tácita, monto final y
 * comisión congelados en decimal exacto, pasos que decide una disputa y la
 * política de calificación del cliente. Contra el ESQUEMA REAL de solicitudes
 * (las migraciones, en SQLite en memoria).
 */

uses(TestCase::class);

const CIERRE_CLIENTE_USUARIO = 900;

function cierreMundo(string $tipoProveedor = 'tercero', string $dronOrigen = 'externo'): int
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'create_sol_solicitud_historial_table',
        'agrega_reputacion_a_sol_clientes',
    ]);

    foreach (['sol_solicitud_historial', 'sol_solicitudes_servicio', 'sol_clientes'] as $tabla) {
        DB::table($tabla)->delete();
    }

    $clienteId = DB::table('sol_clientes')->insertGetId([
        'usuario_id' => CIERRE_CLIENTE_USUARIO, 'tipo' => 'persona', 'razon_social' => 'Cliente Ana', 'estado' => 'activo',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    app()->instance(LecturaProveedores::class, new ProveedoresFalsos([
        7 => new DatosProveedor(7, 700, $tipoProveedor, 'Proveedor Siete', 'aprobado'),
    ]));

    app()->instance(LecturaIntegrantesDeEquipo::class, new class($dronOrigen) implements LecturaIntegrantesDeEquipo
    {
        public function __construct(private string $origen) {}

        public function integrantes(int $equipoId): ?DatosIntegrantesEquipo
        {
            return new DatosIntegrantesEquipo($equipoId, 7, 71, 72, null, $this->origen);
        }
    });

    return $clienteId;
}

/** @param  array<string, mixed>  $atributos */
function cierreSolicitud(int $clienteId, EstadoSolicitud $estado, array $atributos = [], ?Carbon $enElEstadoDesde = null): int
{
    $id = DB::table('sol_solicitudes_servicio')->insertGetId([
        'uuid_cliente' => (string) Str::uuid(), 'cliente_id' => $clienteId, 'terreno_id' => 1, 'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '100.0000', 'producto_indicado' => 'Glifosato', 'aporta_producto' => 'cliente',
        'ventana_desde' => '2026-12-01', 'ventana_hasta' => '2026-12-10', 'urgente' => false,
        'tarifa_hectarea' => '120.0000', 'monto_estimado' => '12000.00',
        'estado' => $estado->value, 'proveedor_id' => 7, 'equipo_id' => 70,
        'created_at' => now(), 'updated_at' => now(),
        ...$atributos,
    ]);

    DB::table('sol_solicitud_historial')->insert([
        'solicitud_id' => $id, 'estado_nuevo' => $estado->value, 'origen' => 'sistema',
        'created_at' => $enElEstadoDesde ?? now(), 'updated_at' => now(),
    ]);

    return $id;
}

// --- Dominio puro ---------------------------------------------------------

test('la comisión depende de quién presta el servicio y del dron', function () {
    $tasas = ['propio' => '0', 'independiente' => '15', 'tercero' => '15', 'dron_vendido' => '10'];

    expect(ComisionDeServicio::porcentaje('propio', false, $tasas))->toBe('0.00')
        ->and(ComisionDeServicio::porcentaje('independiente', false, $tasas))->toBe('15.00')
        ->and(ComisionDeServicio::porcentaje('tercero', false, $tasas))->toBe('15.00')
        ->and(ComisionDeServicio::porcentaje('tercero', true, $tasas))->toBe('10.00')
        ->and(ComisionDeServicio::porcentaje('propio', true, $tasas))->toBe('0.00');
});

test('la comisión y el neto se calculan en decimal exacto con redondeo comercial', function () {
    expect(ComisionDeServicio::monto('12000.00', '15.00'))->toBe('1800.00')
        ->and(ComisionDeServicio::monto('333.33', '15.00'))->toBe('50.00')
        ->and(ComisionDeServicio::monto('0.10', '15.00'))->toBe('0.02')
        ->and(ComisionDeServicio::monto('100.00', '0.00'))->toBe('0.00')
        ->and(ComisionDeServicio::neto('12000.00', '1800.00'))->toBe('10200.00');
});

test('las hectáreas facturadas son las confirmadas con la tolerancia como tope', function () {
    expect(MontoDelServicio::hectareasFacturadas('100.0000', null, '5'))->toBe('100.0000')
        ->and(MontoDelServicio::hectareasFacturadas('100.0000', '92.5000', '5'))->toBe('92.5000')
        ->and(MontoDelServicio::hectareasFacturadas('100.0000', '104.0000', '5'))->toBe('104.0000')
        ->and(MontoDelServicio::hectareasFacturadas('100.0000', '130.0000', '5'))->toBe('105.0000')
        ->and(MontoDelServicio::montoFinal('12000.00', '100.0000', '92.5000'))->toBe('11100.00')
        ->and(MontoDelServicio::montoFinal('12000.00', '100.0000', '100.0000'))->toBe('12000.00');
});

test('la política del cliente avisa, limita tras el plazo y restablece al recuperarse', function (string $promedio, int $conteo, bool $limitado, ?int $dias, string $esperado) {
    expect(PoliticaDeCliente::evaluar($promedio, $conteo, $limitado, $dias, '3.5', 10, 30))->toBe($esperado);
})->with([
    'sin mínimo de calificaciones no se evalúa' => ['2.00', 9, false, null, PoliticaDeCliente::NADA],
    'bajo el umbral avisa' => ['3.40', 10, false, null, PoliticaDeCliente::AVISAR],
    'en el umbral exacto no avisa' => ['3.50', 10, false, null, PoliticaDeCliente::NADA],
    'con aviso reciente espera' => ['3.40', 12, false, 10, PoliticaDeCliente::NADA],
    'con aviso vencido limita' => ['3.40', 12, false, 30, PoliticaDeCliente::LIMITAR],
    'ya limitado no repite' => ['3.00', 12, true, 45, PoliticaDeCliente::NADA],
    'recuperado con aviso restablece' => ['3.80', 12, false, 5, PoliticaDeCliente::RESTABLECER],
    'recuperado y limitado restablece' => ['4.00', 20, true, 40, PoliticaDeCliente::RESTABLECER],
    'sin aviso ni limitación no hace nada' => ['4.50', 20, false, null, PoliticaDeCliente::NADA],
]);

// --- Conformidad, monto y comisión ---------------------------------------

test('la conformidad del cliente completa la solicitud y congela monto final y comisión del tercero', function () {
    Event::fake([SolicitudCompletada::class]);
    $clienteId = cierreMundo('tercero');
    $id = cierreSolicitud($clienteId, EstadoSolicitud::Ejecutada);

    $solicitud = app(ConformarSolicitud::class)->ejecutar(CIERRE_CLIENTE_USUARIO, $id);

    expect($solicitud->estado)->toBe(EstadoSolicitud::Completada)
        ->and($solicitud->monto_final)->toBe('12000.00')
        ->and($solicitud->comision_porcentaje)->toBe('15.00')
        ->and($solicitud->comision_monto)->toBe('1800.00')
        ->and(DB::table('sol_solicitud_historial')->where('solicitud_id', $id)->orderByDesc('id')->value('origen'))->toBe('cliente');

    Event::assertDispatched(SolicitudCompletada::class, fn (SolicitudCompletada $e): bool => $e->solicitudId === $id
        && $e->clienteUsuarioId === CIERRE_CLIENTE_USUARIO && $e->usuariosProveedor === [700] && $e->comisionMonto === '1800.00' && $e->origen === 'cliente');
});

test('un proveedor propio no paga comisión y un dron vendido por Agrocom paga la tasa preferencial', function () {
    $clienteId = cierreMundo('propio');
    $propio = app(ConformarSolicitud::class)->ejecutar(CIERRE_CLIENTE_USUARIO, cierreSolicitud($clienteId, EstadoSolicitud::Ejecutada));

    $clienteId = cierreMundo('tercero', 'vendido_por_agrocom');
    $vendido = app(ConformarSolicitud::class)->ejecutar(CIERRE_CLIENTE_USUARIO, cierreSolicitud($clienteId, EstadoSolicitud::Ejecutada));

    expect($propio->comision_porcentaje)->toBe('0.00')->and($propio->comision_monto)->toBe('0.00')
        ->and($vendido->comision_porcentaje)->toBe('10.00')->and($vendido->comision_monto)->toBe('1200.00');
});

test('dar la conformidad dos veces devuelve la misma solicitud sin repetir el aviso', function () {
    Event::fake([SolicitudCompletada::class]);
    $clienteId = cierreMundo();
    $id = cierreSolicitud($clienteId, EstadoSolicitud::Ejecutada);

    app(ConformarSolicitud::class)->ejecutar(CIERRE_CLIENTE_USUARIO, $id);
    $segunda = app(ConformarSolicitud::class)->ejecutar(CIERRE_CLIENTE_USUARIO, $id);

    expect($segunda->estado)->toBe(EstadoSolicitud::Completada)
        ->and(DB::table('sol_solicitud_historial')->where('solicitud_id', $id)->where('estado_nuevo', 'completada')->count())->toBe(1);
    Event::assertDispatchedTimes(SolicitudCompletada::class, 1);
});

test('no se da conformidad a un servicio ajeno ni a uno que todavía no se ejecutó', function () {
    $clienteId = cierreMundo();
    $enEjecucion = cierreSolicitud($clienteId, EstadoSolicitud::EnEjecucion);

    expect(fn () => app(ConformarSolicitud::class)->ejecutar(CIERRE_CLIENTE_USUARIO, $enEjecucion))->toThrow(TransicionSolicitudNoPermitida::class)
        ->and(fn () => app(ConformarSolicitud::class)->ejecutar(999, $enEjecucion))->toThrow(SolicitudInvalida::class);
});

test('la conformidad tácita completa lo ejecutado hace más de 72 horas y respeta lo demás', function () {
    $clienteId = cierreMundo();
    $vencida = cierreSolicitud($clienteId, EstadoSolicitud::Ejecutada, [], Carbon::now()->subHours(73));
    $reciente = cierreSolicitud($clienteId, EstadoSolicitud::Ejecutada);
    $enDisputa = cierreSolicitud($clienteId, EstadoSolicitud::EnDisputa, [], Carbon::now()->subDays(10));

    $completadas = app(CompletarSolicitudesVencidas::class)->ejecutar();
    $otraVez = app(CompletarSolicitudesVencidas::class)->ejecutar();

    expect($completadas)->toBe(1)->and($otraVez)->toBe(0)
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $vencida)->value('estado'))->toBe('completada')
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $reciente)->value('estado'))->toBe('ejecutada')
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $enDisputa)->value('estado'))->toBe('en_disputa')
        ->and(DB::table('sol_solicitud_historial')->where('solicitud_id', $vencida)->orderByDesc('id')->value('origen'))->toBe('sistema');
});

// --- Pasos de una disputa -------------------------------------------------

test('abrir una disputa mueve una ejecutada a en disputa y no toca una completada', function () {
    $clienteId = cierreMundo();
    $ejecutada = cierreSolicitud($clienteId, EstadoSolicitud::Ejecutada);
    $completada = cierreSolicitud($clienteId, EstadoSolicitud::Completada);
    $resolucion = app(ResolucionDeServicioPorMaquina::class);

    expect($resolucion->abrirDisputa($ejecutada, 'cliente', CIERRE_CLIENTE_USUARIO, 'Cobertura incompleta'))->toBeTrue()
        ->and($resolucion->abrirDisputa($ejecutada, 'cliente', CIERRE_CLIENTE_USUARIO, 'otra vez'))->toBeFalse()
        ->and($resolucion->abrirDisputa($completada, 'proveedor', 700, 'Cobro pendiente'))->toBeFalse()
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $ejecutada)->value('estado'))->toBe('en_disputa')
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $completada)->value('estado'))->toBe('completada');
});

test('cerrar una disputa con reembolso parcial completa la solicitud con su comisión', function () {
    Event::fake([SolicitudCompletada::class, SolicitudCancelada::class]);
    $clienteId = cierreMundo('tercero');
    $id = cierreSolicitud($clienteId, EstadoSolicitud::EnDisputa);

    $datos = app(ResolucionDeServicioPorMaquina::class)->cerrarDisputa($id, false, 55, 'Se repite la mitad');

    expect($datos->estado)->toBe('completada')->and($datos->montoFinal)->toBe('12000.00')->and($datos->comisionMonto)->toBe('1800.00');
    Event::assertDispatched(SolicitudCompletada::class, fn (SolicitudCompletada $e): bool => $e->origen === 'administrador');
    Event::assertNotDispatched(SolicitudCancelada::class);
});

test('cerrar una disputa con reembolso total cancela la solicitud sin comisión', function () {
    Event::fake([SolicitudCompletada::class, SolicitudCancelada::class]);
    $clienteId = cierreMundo('tercero');
    $id = cierreSolicitud($clienteId, EstadoSolicitud::EnDisputa);

    $datos = app(ResolucionDeServicioPorMaquina::class)->cerrarDisputa($id, true, 55, 'Equipo ausente');

    expect($datos->estado)->toBe('cancelada')->and($datos->comisionMonto)->toBeNull()
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $id)->value('cancelada_por'))->toBe('administrador');
    Event::assertDispatched(SolicitudCancelada::class);
    Event::assertNotDispatched(SolicitudCompletada::class);
});

test('cerrar una disputa dos veces no mueve de nuevo la solicitud', function () {
    $clienteId = cierreMundo();
    $id = cierreSolicitud($clienteId, EstadoSolicitud::EnDisputa);
    $resolucion = app(ResolucionDeServicioPorMaquina::class);

    $resolucion->cerrarDisputa($id, false, 55, 'Sin lugar');
    $resolucion->cerrarDisputa($id, true, 55, 'Otra vez');

    expect(DB::table('sol_solicitudes_servicio')->where('id', $id)->value('estado'))->toBe('completada')
        ->and(app(LecturaServicios::class)->datos($id)->completadaEn)->not->toBeNull();
});

// --- Reputación del cliente ----------------------------------------------

test('un promedio bajo avisa, pasado el plazo limita la publicación y al recuperarse se restablece', function () {
    Event::fake([ClienteAvisadoPorCalificacion::class, PublicacionDeClienteLimitada::class]);
    $clienteId = cierreMundo();
    $reputacion = app(ReputacionDeClientePorSolicitudes::class);

    $reputacion->actualizar($clienteId, '3.20', 12);
    $reputacion->actualizar($clienteId, '3.20', 12);

    $cliente = DB::table('sol_clientes')->find($clienteId);
    expect($cliente->aviso_calificacion_en)->not->toBeNull()->and($cliente->publicacion_limitada_en)->toBeNull();
    Event::assertDispatchedTimes(ClienteAvisadoPorCalificacion::class, 1);

    DB::table('sol_clientes')->where('id', $clienteId)->update(['aviso_calificacion_en' => Carbon::now()->subDays(31)]);
    $reputacion->actualizar($clienteId, '3.20', 12);
    $reputacion->actualizar($clienteId, '3.20', 12);

    expect(DB::table('sol_clientes')->find($clienteId)->publicacion_limitada_en)->not->toBeNull();
    Event::assertDispatchedTimes(PublicacionDeClienteLimitada::class, 1);

    $reputacion->actualizar($clienteId, '4.10', 14);

    $cliente = DB::table('sol_clientes')->find($clienteId);
    expect($cliente->aviso_calificacion_en)->toBeNull()->and($cliente->publicacion_limitada_en)->toBeNull()
        ->and($cliente->calificacion_promedio)->toEqual('4.10');
});

test('un cliente con la publicación limitada no publica solicitudes', function () {
    $clienteId = cierreMundo();
    $id = cierreSolicitud($clienteId, EstadoSolicitud::Borrador);
    DB::table('sol_clientes')->where('id', $clienteId)->update(['publicacion_limitada_en' => now()]);

    expect(fn () => app(PublicarSolicitud::class)->ejecutar(CIERRE_CLIENTE_USUARIO, $id))
        ->toRechazarCon(SolicitudInvalida::class, 'solicitudes.errores.publicacion_limitada');
});
