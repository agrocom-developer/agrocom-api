<?php

use App\Dominios\Disputas\Aplicacion\AbrirDisputa;
use App\Dominios\Disputas\Aplicacion\AgregarMensajeDeDisputa;
use App\Dominios\Disputas\Aplicacion\ApelarResolucion;
use App\Dominios\Disputas\Aplicacion\ConsultarDisputas;
use App\Dominios\Disputas\Aplicacion\ResolverDisputa;
use App\Dominios\Disputas\Aplicacion\RetirarDisputa;
use App\Dominios\Disputas\Aplicacion\TomarDisputa;
use App\Dominios\Disputas\Contratos\Eventos\DisputaAbierta;
use App\Dominios\Disputas\Contratos\Eventos\DisputaApelada;
use App\Dominios\Disputas\Contratos\Eventos\DisputaResuelta;
use App\Dominios\Disputas\Dominio\EstadoDisputa;
use App\Dominios\Disputas\Dominio\Excepciones\DisputaInvalida;
use App\Dominios\Disputas\Dominio\ParteDeDisputa;
use App\Dominios\Disputas\Dominio\PlazosDeDisputa;
use App\Dominios\Disputas\Dominio\ReglaDeResolucion;
use App\Dominios\Disputas\Dominio\ResultadoDisputa;
use App\Dominios\Disputas\Dominio\TransicionesDisputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\Disputa;
use App\Dominios\Flota\Contratos\DatosIntegrantesEquipo;
use App\Dominios\Flota\Contratos\LecturaIntegrantesDeEquipo;
use App\Dominios\Proveedores\Contratos\DatosPiloto;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Soporte\Esquema;
use Tests\Soporte\ProveedoresFalsos;
use Tests\TestCase;

/*
 * Disputas y reembolsos (política de disputas, ADR 0033 y 0039): apertura de cada
 * lado con sus plazos, mediación, resolución con reembolso en decimal exacto y
 * ajuste de comisión, retiro y apelación única. Contra el ESQUEMA REAL de
 * solicitudes y disputas (las migraciones, en SQLite en memoria); los módulos
 * vecinos de proveedores y flota entran por sus contratos.
 *
 * Mundo: cliente 1 (cuenta 900) con otra cuenta de cliente 2 (901); proveedor 7
 * (titular 700, piloto con cuenta 710), proveedor tercero que cobra 15 %; otro
 * proveedor 8 (titular 800). El servicio vale 12.000,00.
 */

uses(TestCase::class);

const DSP_CLIENTE = 900;
const DSP_OTRO_CLIENTE = 901;
const DSP_TITULAR = 700;
const DSP_PILOTO = 710;
const DSP_OTRO_PROVEEDOR = 800;
const DSP_ADMIN = 5;

function dspMundo(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'create_sol_solicitud_historial_table',
        'agrega_reputacion_a_sol_clientes',
        'create_dsp_disputas_table',
        'create_dsp_mensajes_table',
        'create_dsp_resoluciones_table',
        'create_dsp_historial_table',
    ]);

    foreach (['dsp_historial', 'dsp_resoluciones', 'dsp_mensajes', 'dsp_disputas', 'sol_solicitud_historial', 'sol_solicitudes_servicio', 'sol_clientes'] as $tabla) {
        DB::table($tabla)->delete();
    }

    foreach ([1 => DSP_CLIENTE, 2 => DSP_OTRO_CLIENTE] as $id => $usuario) {
        DB::table('sol_clientes')->insert(['id' => $id, 'usuario_id' => $usuario, 'tipo' => 'persona', 'razon_social' => "Cliente {$id}", 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now()]);
    }

    app()->instance(LecturaProveedores::class, new ProveedoresFalsos(
        [
            7 => new DatosProveedor(7, DSP_TITULAR, 'tercero', 'Proveedor Siete', 'aprobado'),
            8 => new DatosProveedor(8, DSP_OTRO_PROVEEDOR, 'tercero', 'Proveedor Ocho', 'aprobado'),
        ],
        [71 => new DatosPiloto(71, 7, DSP_PILOTO, 'Piloto Pedro', 'aprobado')],
    ));

    app()->instance(LecturaIntegrantesDeEquipo::class, new class implements LecturaIntegrantesDeEquipo
    {
        public function integrantes(int $equipoId): ?DatosIntegrantesEquipo
        {
            return new DatosIntegrantesEquipo($equipoId, 7, 71, 701, null, 'externo');
        }
    });
}

function dspSolicitud(string $estado, ?Carbon $enElEstadoDesde = null, ?string $comision = '1800.00'): int
{
    $id = DB::table('sol_solicitudes_servicio')->insertGetId([
        'uuid_cliente' => (string) Str::uuid(), 'cliente_id' => 1, 'terreno_id' => 1, 'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '100.0000', 'producto_indicado' => 'Glifosato', 'aporta_producto' => 'cliente',
        'ventana_desde' => '2026-12-01', 'ventana_hasta' => '2026-12-10', 'urgente' => false,
        'monto_estimado' => '12000.00', 'estado' => $estado, 'proveedor_id' => 7, 'equipo_id' => 70,
        'monto_final' => $estado === 'completada' ? '12000.00' : null,
        'comision_porcentaje' => $estado === 'completada' ? '15.00' : null,
        'comision_monto' => $estado === 'completada' ? $comision : null,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('sol_solicitud_historial')->insert([
        'solicitud_id' => $id, 'estado_nuevo' => $estado, 'origen' => 'sistema',
        'created_at' => $enElEstadoDesde ?? Carbon::now()->subHours(2), 'updated_at' => now(),
    ]);

    return $id;
}

function dspEstadoDeSolicitud(int $id): string
{
    return (string) DB::table('sol_solicitudes_servicio')->where('id', $id)->value('estado');
}

function dspAbrirComoCliente(int $solicitud, string $motivo = 'cobertura_incompleta', ?string $monto = null): Disputa
{
    return app(AbrirDisputa::class)->ejecutar(DSP_CLIENTE, ParteDeDisputa::Cliente, $solicitud, $motivo, 'Quedaron franjas sin cubrir', $monto);
}

// --- Dominio puro ---------------------------------------------------------

test('cada resultado admite solo los reembolsos que le corresponden', function (ResultadoDisputa $resultado, string $reembolso, ?string $error) {
    expect(ReglaDeResolucion::error($resultado, '12000.00', $reembolso))->toBe($error);
})->with([
    'a favor del proveedor sin reembolso' => [ResultadoDisputa::AFavorProveedor, '0.00', null],
    'a favor del proveedor con reembolso' => [ResultadoDisputa::AFavorProveedor, '100.00', 'sin_reembolso'],
    'sin lugar sin reembolso' => [ResultadoDisputa::SinLugar, '0.00', null],
    'sin lugar con reembolso' => [ResultadoDisputa::SinLugar, '1.00', 'sin_reembolso'],
    'a favor del cliente total' => [ResultadoDisputa::AFavorCliente, '12000.00', null],
    'a favor del cliente parte' => [ResultadoDisputa::AFavorCliente, '4000.00', null],
    'a favor del cliente sin monto' => [ResultadoDisputa::AFavorCliente, '0.00', 'requiere_reembolso'],
    'a favor del cliente de más' => [ResultadoDisputa::AFavorCliente, '12000.01', 'excede_monto'],
    'parcial con monto' => [ResultadoDisputa::Parcial, '3000.00', null],
    'parcial sin monto' => [ResultadoDisputa::Parcial, '0.00', 'requiere_reembolso'],
    'parcial por el total' => [ResultadoDisputa::Parcial, '12000.00', 'parcial_no_total'],
    'reembolso negativo' => [ResultadoDisputa::Parcial, '-1.00', 'negativo'],
]);

test('solo un reembolso del monto completo a favor del cliente cancela, y la comisión se ajusta en proporción', function () {
    expect(ReglaDeResolucion::esReembolsoTotal(ResultadoDisputa::AFavorCliente, '12000.00', '12000.00'))->toBeTrue()
        ->and(ReglaDeResolucion::esReembolsoTotal(ResultadoDisputa::AFavorCliente, '12000.00', '11999.99'))->toBeFalse()
        ->and(ReglaDeResolucion::esReembolsoTotal(ResultadoDisputa::Parcial, '12000.00', '12000.00'))->toBeFalse()
        ->and(ReglaDeResolucion::ajusteDeComision('1800.00', '12000.00', '3000.00'))->toBe('450.00')
        ->and(ReglaDeResolucion::ajusteDeComision('1800.00', '12000.00', '12000.00'))->toBe('1800.00')
        ->and(ReglaDeResolucion::ajusteDeComision('0.00', '12000.00', '3000.00'))->toBe('0.00')
        ->and(ReglaDeResolucion::ajusteDeComision(null, '12000.00', '3000.00'))->toBe('0.00')
        ->and(ReglaDeResolucion::ajusteDeComision('1800.00', null, '3000.00'))->toBe('0.00')
        ->and(ReglaDeResolucion::ajusteDeComision('10.00', '33.33', '11.11'))->toBe('3.33');
});

test('la tabla de transiciones de la disputa solo admite el recorrido de la especificación', function () {
    expect(TransicionesDisputa::permitida(EstadoDisputa::Abierta, EstadoDisputa::EnRevision))->toBeTrue()
        ->and(TransicionesDisputa::permitida(EstadoDisputa::Abierta, EstadoDisputa::Retirada))->toBeTrue()
        ->and(TransicionesDisputa::permitida(EstadoDisputa::EnRevision, EstadoDisputa::Resuelta))->toBeTrue()
        ->and(TransicionesDisputa::permitida(EstadoDisputa::Resuelta, EstadoDisputa::EnRevision))->toBeTrue()
        ->and(TransicionesDisputa::permitida(EstadoDisputa::Abierta, EstadoDisputa::Resuelta))->toBeFalse()
        ->and(TransicionesDisputa::permitida(EstadoDisputa::Retirada, EstadoDisputa::Abierta))->toBeFalse()
        ->and(TransicionesDisputa::destinosDesde(EstadoDisputa::Retirada))->toBe([]);
});

test('los plazos de la política se miden desde el momento correcto', function () {
    $ejecutada = new DateTimeImmutable('2026-12-01 10:00:00');

    expect(PlazosDeDisputa::clientePuedeAbrir($ejecutada, new DateTimeImmutable('2026-12-04 10:00:00'), 72))->toBeTrue()
        ->and(PlazosDeDisputa::clientePuedeAbrir($ejecutada, new DateTimeImmutable('2026-12-04 10:00:01'), 72))->toBeFalse()
        ->and(PlazosDeDisputa::proveedorPuedeAbrir(null, new DateTimeImmutable('2030-01-01'), 30))->toBeTrue()
        ->and(PlazosDeDisputa::proveedorPuedeAbrir($ejecutada, new DateTimeImmutable('2027-01-01 10:00:00'), 30))->toBeFalse()
        ->and(PlazosDeDisputa::respondeAntesDe($ejecutada, 48)->format('Y-m-d H:i'))->toBe('2026-12-03 10:00')
        ->and(PlazosDeDisputa::apelableHasta($ejecutada, 7)->format('Y-m-d'))->toBe('2026-12-08');
});

// --- Apertura -------------------------------------------------------------

test('el cliente abre una disputa sobre un servicio ejecutado: la solicitud pasa a en disputa y se avisa a la contraparte', function () {
    Event::fake([DisputaAbierta::class]);
    dspMundo();
    $solicitud = dspSolicitud('ejecutada');

    $disputa = dspAbrirComoCliente($solicitud);

    expect($disputa->estado)->toBe(EstadoDisputa::Abierta)
        ->and($disputa->monto_en_disputa)->toBe('12000.00')
        ->and($disputa->movio_solicitud)->toBeTrue()
        ->and($disputa->proveedor_id)->toBe(7)
        ->and($disputa->responde_antes_de)->not->toBeNull()
        ->and(dspEstadoDeSolicitud($solicitud))->toBe('en_disputa')
        ->and(DB::table('dsp_historial')->where('disputa_id', $disputa->id)->count())->toBe(1)
        ->and(DB::table('sol_solicitud_historial')->where('solicitud_id', $solicitud)->orderByDesc('id')->value('origen'))->toBe('cliente');
    Event::assertDispatched(DisputaAbierta::class, fn (DisputaAbierta $e): bool => $e->abiertaPor === 'cliente' && $e->usuariosContraparte === [DSP_TITULAR, DSP_PILOTO]);
});

test('el monto en disputa no pasa del monto del servicio', function () {
    dspMundo();
    $solicitud = dspSolicitud('ejecutada');

    expect(fn () => dspAbrirComoCliente($solicitud, 'cobertura_incompleta', '12000.01'))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.monto_invalido')
        ->and(fn () => dspAbrirComoCliente($solicitud, 'cobertura_incompleta', '0'))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.monto_invalido')
        ->and(dspAbrirComoCliente($solicitud, 'cobertura_incompleta', '3000.5')->monto_en_disputa)->toBe('3000.50');
});

test('hay una sola disputa por solicitud', function () {
    dspMundo();
    $solicitud = dspSolicitud('ejecutada');
    dspAbrirComoCliente($solicitud);

    try {
        dspAbrirComoCliente($solicitud);
        $this->fail('debió rechazarse');
    } catch (DisputaInvalida $excepcion) {
        expect($excepcion->esConflicto)->toBeTrue();
    }

    expect(Disputa::query()->count())->toBe(1);
});

test('el cliente solo alega los motivos suyos y solo sobre su servicio ejecutado dentro del plazo', function () {
    dspMundo();
    $enEjecucion = dspSolicitud('en_ejecucion');
    $completada = dspSolicitud('completada');
    $vencida = dspSolicitud('ejecutada', Carbon::now()->subHours(73));
    $buena = dspSolicitud('ejecutada');

    expect(fn () => dspAbrirComoCliente($buena, 'cobro_pendiente'))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.motivo_invalido')
        ->and(fn () => dspAbrirComoCliente($enEjecucion))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.servicio_no_disputable')
        ->and(fn () => dspAbrirComoCliente($completada))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.servicio_no_disputable')
        ->and(fn () => dspAbrirComoCliente($vencida))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.plazo_vencido')
        ->and(fn () => app(AbrirDisputa::class)->ejecutar(DSP_CLIENTE, ParteDeDisputa::Cliente, $buena, 'cobertura_incompleta', '  '))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.texto_requerido')
        ->and(Disputa::query()->count())->toBe(0)
        ->and(dspEstadoDeSolicitud($buena))->toBe('ejecutada');
});

test('el servicio de otro cliente responde como si no existiera', function () {
    dspMundo();
    $solicitud = dspSolicitud('ejecutada');

    try {
        app(AbrirDisputa::class)->ejecutar(DSP_OTRO_CLIENTE, ParteDeDisputa::Cliente, $solicitud, 'cobertura_incompleta', 'Detalle');
        $this->fail('debió rechazarse');
    } catch (DisputaInvalida $excepcion) {
        expect($excepcion->esAjeno)->toBeTrue();
    }
});

test('el proveedor reclama un cobro pendiente sobre un servicio completado sin mover la solicitud', function () {
    Event::fake([DisputaAbierta::class]);
    dspMundo();
    $solicitud = dspSolicitud('completada');

    $disputa = app(AbrirDisputa::class)->ejecutar(DSP_PILOTO, ParteDeDisputa::Proveedor, $solicitud, 'cobro_pendiente', 'El cliente no pagó');

    expect($disputa->abierta_por_rol)->toBe(ParteDeDisputa::Proveedor)
        ->and($disputa->movio_solicitud)->toBeFalse()
        ->and(dspEstadoDeSolicitud($solicitud))->toBe('completada');
    Event::assertDispatched(DisputaAbierta::class, fn (DisputaAbierta $e): bool => $e->usuariosContraparte === [DSP_CLIENTE]);
});

test('el proveedor que disputa un servicio ejecutado lo pasa a en disputa', function () {
    dspMundo();
    $solicitud = dspSolicitud('ejecutada');

    $disputa = app(AbrirDisputa::class)->ejecutar(DSP_TITULAR, ParteDeDisputa::Proveedor, $solicitud, 'cobro_pendiente', 'El cliente no paga');

    expect($disputa->movio_solicitud)->toBeTrue()->and(dspEstadoDeSolicitud($solicitud))->toBe('en_disputa');
});

test('el proveedor solo alega sus motivos, en los estados que corresponden y dentro de su ventana', function () {
    dspMundo();
    $enCamino = dspSolicitud('en_camino');
    $ejecutada = dspSolicitud('ejecutada');
    $antigua = dspSolicitud('completada', Carbon::now()->subDays(31));

    expect(app(AbrirDisputa::class)->ejecutar(DSP_TITULAR, ParteDeDisputa::Proveedor, $enCamino, 'acceso_negado', 'Portón cerrado')->movio_solicitud)->toBeFalse()
        ->and(fn () => app(AbrirDisputa::class)->ejecutar(DSP_TITULAR, ParteDeDisputa::Proveedor, $ejecutada, 'calificacion_abusiva', 'Insultos'))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.servicio_no_disputable')
        ->and(fn () => app(AbrirDisputa::class)->ejecutar(DSP_TITULAR, ParteDeDisputa::Proveedor, $ejecutada, 'cobertura_incompleta', 'No es mío'))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.motivo_invalido')
        ->and(fn () => app(AbrirDisputa::class)->ejecutar(DSP_TITULAR, ParteDeDisputa::Proveedor, $antigua, 'cobro_pendiente', 'Hace mucho'))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.plazo_vencido');
});

test('un proveedor que no prestó el servicio no abre la disputa', function () {
    dspMundo();
    $solicitud = dspSolicitud('completada');

    try {
        app(AbrirDisputa::class)->ejecutar(DSP_OTRO_PROVEEDOR, ParteDeDisputa::Proveedor, $solicitud, 'cobro_pendiente', 'No fue mío');
        $this->fail('debió rechazarse');
    } catch (DisputaInvalida $excepcion) {
        expect($excepcion->esAjeno)->toBeTrue();
    }
});

// --- Mensajes y mediación -------------------------------------------------

test('las partes y soporte escriben mientras la disputa está viva, y nadie ajeno entra', function () {
    dspMundo();
    $disputa = dspAbrirComoCliente(dspSolicitud('ejecutada'));

    app(AgregarMensajeDeDisputa::class)->comoParte(DSP_PILOTO, ParteDeDisputa::Proveedor, $disputa->id, 'El vuelo cubrió todo');
    app(AgregarMensajeDeDisputa::class)->comoSoporte(DSP_ADMIN, $disputa->id, '¿Tienen el registro del dron?', true);

    expect(DB::table('dsp_mensajes')->where('disputa_id', $disputa->id)->orderBy('id')->get(['autor_rol', 'tipo'])->map(fn ($m): string => $m->autor_rol.':'.$m->tipo)->all())->toBe(['proveedor:mensaje', 'soporte:informacion']);

    try {
        app(AgregarMensajeDeDisputa::class)->comoParte(DSP_OTRO_PROVEEDOR, ParteDeDisputa::Proveedor, $disputa->id, 'Me meto');
        $this->fail('debió rechazarse');
    } catch (DisputaInvalida $excepcion) {
        expect($excepcion->esAjeno)->toBeTrue();
    }

    expect(fn () => app(AgregarMensajeDeDisputa::class)->comoParte(DSP_CLIENTE, ParteDeDisputa::Cliente, $disputa->id, ' '))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.texto_requerido');
});

test('soporte toma la disputa una sola vez y queda registrado quién la atiende', function () {
    dspMundo();
    $disputa = dspAbrirComoCliente(dspSolicitud('ejecutada'));

    $tomada = app(TomarDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id);
    $otraVez = app(TomarDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id);

    expect($tomada->estado)->toBe(EstadoDisputa::EnRevision)
        ->and($tomada->atendida_por)->toBe(DSP_ADMIN)
        ->and($otraVez->estado)->toBe(EstadoDisputa::EnRevision)
        ->and(DB::table('dsp_historial')->where('disputa_id', $disputa->id)->count())->toBe(2);
});

// --- Resolución -----------------------------------------------------------

test('un reembolso parcial completa la solicitud, congela su comisión y ajusta la comisión en proporción', function () {
    Event::fake([DisputaResuelta::class]);
    dspMundo();
    $solicitud = dspSolicitud('ejecutada');
    $disputa = dspAbrirComoCliente($solicitud);

    $resuelta = app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id, ResultadoDisputa::Parcial, 'Faltó cubrir la franja norte', '3000');

    $resolucion = DB::table('dsp_resoluciones')->where('disputa_id', $disputa->id)->first();
    expect($resuelta->estado)->toBe(EstadoDisputa::Resuelta)
        ->and($resuelta->apelable_hasta)->not->toBeNull()
        ->and(dspEstadoDeSolicitud($solicitud))->toBe('completada')
        ->and(DB::table('sol_solicitudes_servicio')->where('id', $solicitud)->value('comision_monto'))->toEqual('1800.00')
        ->and($resolucion->monto_reembolso)->toEqual('3000.00')
        ->and($resolucion->comision_ajuste)->toEqual('450.00')
        ->and($resolucion->resuelta_por)->toBe(DSP_ADMIN)
        ->and(DB::table('dsp_historial')->where('disputa_id', $disputa->id)->pluck('estado_nuevo')->all())->toBe(['abierta', 'en_revision', 'resuelta']);
    Event::assertDispatched(DisputaResuelta::class, fn (DisputaResuelta $e): bool => $e->resultado === 'parcial' && $e->montoReembolso === '3000.00' && $e->usuariosDestinatarios === [DSP_CLIENTE, DSP_TITULAR, DSP_PILOTO]);
});

test('un reembolso total a favor del cliente cancela la solicitud sin comisión que ajustar', function () {
    dspMundo();
    $solicitud = dspSolicitud('ejecutada');
    $disputa = dspAbrirComoCliente($solicitud, 'equipo_ausente');

    app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id, ResultadoDisputa::AFavorCliente, 'El equipo nunca se presentó', '12000.00', malaFeCliente: true);

    $resolucion = DB::table('dsp_resoluciones')->where('disputa_id', $disputa->id)->first();
    expect(dspEstadoDeSolicitud($solicitud))->toBe('cancelada')
        ->and($resolucion->comision_ajuste)->toEqual('0.00')
        ->and((bool) $resolucion->mala_fe_cliente)->toBeFalse();
});

test('a favor del proveedor o sin lugar completa la solicitud sin reembolso, y la mala fe solo se registra sin lugar', function () {
    dspMundo();
    $primera = dspSolicitud('ejecutada');
    $segunda = dspSolicitud('ejecutada');
    $a = dspAbrirComoCliente($primera);
    $b = dspAbrirComoCliente($segunda, 'trato_inadecuado');

    app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $a->id, ResultadoDisputa::AFavorProveedor, 'El registro prueba la cobertura', '0', malaFeCliente: true);
    app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $b->id, ResultadoDisputa::SinLugar, 'No hay prueba del reclamo', '', malaFeCliente: true);

    expect(dspEstadoDeSolicitud($primera))->toBe('completada')
        ->and(dspEstadoDeSolicitud($segunda))->toBe('completada')
        ->and((bool) DB::table('dsp_resoluciones')->where('disputa_id', $a->id)->value('mala_fe_cliente'))->toBeFalse()
        ->and((bool) DB::table('dsp_resoluciones')->where('disputa_id', $b->id)->value('mala_fe_cliente'))->toBeTrue()
        ->and(DB::table('dsp_resoluciones')->where('disputa_id', $b->id)->value('monto_reembolso'))->toEqual('0.00');
});

test('un daño probado se deriva al seguro del proveedor', function () {
    dspMundo();
    $disputa = dspAbrirComoCliente(dspSolicitud('ejecutada'), 'dano_a_cultivos_o_terceros');

    app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id, ResultadoDisputa::AFavorCliente, 'Daño comprobado en el lote vecino', '5000', derivadaASeguro: true);

    expect((bool) DB::table('dsp_resoluciones')->where('disputa_id', $disputa->id)->value('derivada_a_seguro'))->toBeTrue();
});

test('resolver exige un reembolso que corresponda al resultado y un motivo, y una disputa se resuelve una sola vez por ronda', function () {
    dspMundo();
    $solicitud = dspSolicitud('ejecutada');
    $disputa = dspAbrirComoCliente($solicitud);

    expect(fn () => app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id, ResultadoDisputa::SinLugar, 'Motivo', '100'))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.reembolso.sin_reembolso')
        ->and(fn () => app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id, ResultadoDisputa::AFavorCliente, 'Motivo', '13000'))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.reembolso.excede_monto')
        ->and(fn () => app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id, ResultadoDisputa::SinLugar, ' ', '0'))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.motivo_requerido')
        ->and(dspEstadoDeSolicitud($solicitud))->toBe('en_disputa')
        ->and(Disputa::query()->find($disputa->id)->estado)->toBe(EstadoDisputa::Abierta);

    app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id, ResultadoDisputa::SinLugar, 'No procede', '0');

    try {
        app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id, ResultadoDisputa::AFavorCliente, 'Cambié de idea', '12000');
        $this->fail('debió rechazarse');
    } catch (DisputaInvalida $excepcion) {
        expect($excepcion->esConflicto)->toBeTrue();
    }

    expect(DB::table('dsp_resoluciones')->where('disputa_id', $disputa->id)->count())->toBe(1)
        ->and(dspEstadoDeSolicitud($solicitud))->toBe('completada');
});

test('una disputa del proveedor sobre un servicio ya completado se resuelve sin mover la solicitud', function () {
    dspMundo();
    $solicitud = dspSolicitud('completada');
    $disputa = app(AbrirDisputa::class)->ejecutar(DSP_TITULAR, ParteDeDisputa::Proveedor, $solicitud, 'cobro_pendiente', 'No pagó');

    app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id, ResultadoDisputa::AFavorProveedor, 'El cliente debe pagar', '0');

    expect(dspEstadoDeSolicitud($solicitud))->toBe('completada')
        ->and(Disputa::query()->find($disputa->id)->estado)->toBe(EstadoDisputa::Resuelta);
});

// --- Retiro y apelación ---------------------------------------------------

test('quien abrió la disputa la retira y la solicitud se completa; es idempotente', function () {
    Event::fake([DisputaResuelta::class]);
    dspMundo();
    $solicitud = dspSolicitud('ejecutada');
    $disputa = dspAbrirComoCliente($solicitud);

    $retirada = app(RetirarDisputa::class)->ejecutar(DSP_CLIENTE, ParteDeDisputa::Cliente, $disputa->id);
    app(RetirarDisputa::class)->ejecutar(DSP_CLIENTE, ParteDeDisputa::Cliente, $disputa->id);

    expect($retirada->estado)->toBe(EstadoDisputa::Retirada)
        ->and(dspEstadoDeSolicitud($solicitud))->toBe('completada');
    Event::assertDispatchedTimes(DisputaResuelta::class, 1);
    Event::assertDispatched(DisputaResuelta::class, fn (DisputaResuelta $e): bool => $e->resultado === 'retirada');
});

test('la contraparte no retira una disputa ajena y una resuelta no se retira', function () {
    dspMundo();
    $disputa = dspAbrirComoCliente(dspSolicitud('ejecutada'));

    expect(fn () => app(RetirarDisputa::class)->ejecutar(DSP_TITULAR, ParteDeDisputa::Proveedor, $disputa->id))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.solo_quien_la_abrio');

    app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id, ResultadoDisputa::SinLugar, 'No procede', '0');

    expect(fn () => app(RetirarDisputa::class)->ejecutar(DSP_CLIENTE, ParteDeDisputa::Cliente, $disputa->id))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.no_se_retira');
});

test('las partes apelan una sola vez dentro de siete días y la segunda resolución no mueve la solicitud', function () {
    Event::fake([DisputaApelada::class]);
    dspMundo();
    $solicitud = dspSolicitud('ejecutada');
    $disputa = dspAbrirComoCliente($solicitud);
    app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id, ResultadoDisputa::SinLugar, 'No procede', '0');

    $apelada = app(ApelarResolucion::class)->ejecutar(DSP_CLIENTE, ParteDeDisputa::Cliente, $disputa->id, 'El registro del dron muestra la franja sin cubrir');

    expect($apelada->estado)->toBe(EstadoDisputa::EnRevision)
        ->and($apelada->ronda)->toBe(2)
        ->and($apelada->apelable_hasta)->toBeNull()
        ->and(DB::table('dsp_mensajes')->where('disputa_id', $disputa->id)->value('tipo'))->toBe('apelacion');
    Event::assertDispatched(DisputaApelada::class, fn (DisputaApelada $e): bool => $e->apeladaPor === 'cliente' && $e->usuariosContraparte === [DSP_TITULAR, DSP_PILOTO]);

    app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id, ResultadoDisputa::Parcial, 'La prueba nueva lo justifica', '2000');

    expect(DB::table('dsp_resoluciones')->where('disputa_id', $disputa->id)->orderBy('ronda')->pluck('resultado')->all())->toBe(['sin_lugar', 'parcial'])
        ->and(DB::table('dsp_resoluciones')->where('disputa_id', $disputa->id)->where('ronda', 2)->value('comision_ajuste'))->toEqual('300.00')
        ->and(dspEstadoDeSolicitud($solicitud))->toBe('completada')
        ->and(Disputa::query()->find($disputa->id)->apelable_hasta)->toBeNull()
        ->and(fn () => app(ApelarResolucion::class)->ejecutar(DSP_TITULAR, ParteDeDisputa::Proveedor, $disputa->id, 'Apelo yo también'))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.no_se_apela');
});

test('no se apela fuera de plazo, sin pruebas ni una disputa que no está resuelta', function () {
    dspMundo();
    $disputa = dspAbrirComoCliente(dspSolicitud('ejecutada'));

    expect(fn () => app(ApelarResolucion::class)->ejecutar(DSP_CLIENTE, ParteDeDisputa::Cliente, $disputa->id, 'Pruebas'))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.no_se_apela');

    app(ResolverDisputa::class)->ejecutar(DSP_ADMIN, $disputa->id, ResultadoDisputa::SinLugar, 'No procede', '0');

    expect(fn () => app(ApelarResolucion::class)->ejecutar(DSP_CLIENTE, ParteDeDisputa::Cliente, $disputa->id, ' '))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.texto_requerido');

    Disputa::query()->whereKey($disputa->id)->update(['apelable_hasta' => Carbon::now()->subMinute()]);

    expect(fn () => app(ApelarResolucion::class)->ejecutar(DSP_CLIENTE, ParteDeDisputa::Cliente, $disputa->id, 'Pruebas'))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.apelacion_vencida');
});

// --- Lo que ve cada lado --------------------------------------------------

test('cada lado ve solo sus disputas y una ajena responde como si no existiera', function () {
    dspMundo();
    $propia = dspAbrirComoCliente(dspSolicitud('ejecutada'));
    $consulta = app(ConsultarDisputas::class);

    expect($consulta->propias(DSP_CLIENTE, ParteDeDisputa::Cliente)->total())->toBe(1)
        ->and($consulta->propias(DSP_OTRO_CLIENTE, ParteDeDisputa::Cliente)->total())->toBe(0)
        ->and($consulta->propias(DSP_PILOTO, ParteDeDisputa::Proveedor)->total())->toBe(1)
        ->and($consulta->propias(DSP_OTRO_PROVEEDOR, ParteDeDisputa::Proveedor)->total())->toBe(0)
        ->and($consulta->propias(DSP_CLIENTE, ParteDeDisputa::Cliente, 'resuelta')->total())->toBe(0)
        ->and($consulta->una(DSP_TITULAR, ParteDeDisputa::Proveedor, $propia->id)->id)->toBe($propia->id)
        ->and($consulta->deLaSolicitud(DSP_CLIENTE, ParteDeDisputa::Cliente, $propia->solicitud_id)->id)->toBe($propia->id)
        ->and(fn () => $consulta->una(DSP_OTRO_CLIENTE, ParteDeDisputa::Cliente, $propia->id))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.disputa_ajena')
        ->and(fn () => $consulta->una(DSP_OTRO_PROVEEDOR, ParteDeDisputa::Proveedor, $propia->id))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.disputa_ajena')
        ->and(fn () => $consulta->deLaSolicitud(DSP_OTRO_CLIENTE, ParteDeDisputa::Cliente, $propia->solicitud_id))->toRechazarCon(DisputaInvalida::class, 'disputas.errores.disputa_ajena');
});
