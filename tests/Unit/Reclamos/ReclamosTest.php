<?php

use App\Dominios\Flota\Contratos\DatosIntegrantesEquipo;
use App\Dominios\Flota\Contratos\LecturaIntegrantesDeEquipo;
use App\Dominios\Proveedores\Contratos\DatosPiloto;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Reclamos\Aplicacion\AbrirReclamo;
use App\Dominios\Reclamos\Aplicacion\AgregarMensajeDeReclamo;
use App\Dominios\Reclamos\Aplicacion\ApelarResolucion;
use App\Dominios\Reclamos\Aplicacion\ConsultarReclamos;
use App\Dominios\Reclamos\Aplicacion\ResolverReclamo;
use App\Dominios\Reclamos\Aplicacion\RetirarReclamo;
use App\Dominios\Reclamos\Aplicacion\TomarReclamo;
use App\Dominios\Reclamos\Contratos\Eventos\ReclamoAbierto;
use App\Dominios\Reclamos\Contratos\Eventos\ReclamoApelado;
use App\Dominios\Reclamos\Contratos\Eventos\ReclamoResuelto;
use App\Dominios\Reclamos\Dominio\EstadoReclamo;
use App\Dominios\Reclamos\Dominio\Excepciones\ReclamoInvalido;
use App\Dominios\Reclamos\Dominio\ParteDeReclamo;
use App\Dominios\Reclamos\Dominio\PlazosDeReclamo;
use App\Dominios\Reclamos\Dominio\ReglaDeResolucion;
use App\Dominios\Reclamos\Dominio\ResultadoReclamo;
use App\Dominios\Reclamos\Dominio\TransicionesReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\Reclamo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Soporte\Esquema;
use Tests\Soporte\ProveedoresFalsos;
use Tests\TestCase;

/*
 * Reclamos y reembolsos (política de reclamos, ADR 0033 y 0039): apertura de cada
 * lado con sus plazos, mediación, resolución con reembolso en decimal exacto y
 * ajuste de comisión, retiro y apelación única. Contra el ESQUEMA REAL de
 * solicitudes y reclamos (las migraciones, en SQLite en memoria); los módulos
 * vecinos de proveedores y flota entran por sus contratos.
 *
 * Mundo: cliente 1 (cuenta 900) con otra cuenta de cliente 2 (901); proveedor 7
 * (titular 700, piloto con cuenta 710), proveedor tercero que cobra 15 %; otro
 * proveedor 8 (titular 800). El servicio vale 12.000,00.
 */

uses(TestCase::class);

const RCL_CLIENTE = 900;
const RCL_OTRO_CLIENTE = 901;
const RCL_TITULAR = 700;
const RCL_PILOTO = 710;
const RCL_OTRO_PROVEEDOR = 800;
const RCL_ADMIN = 5;

function rclMundo(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'agrega_negociacion_a_sol_solicitudes_servicio',
        'create_neg_tablas_de_negociacion',
        'create_sol_solicitud_historial_table',
        'agrega_reputacion_a_sol_clientes',
        'create_dsp_disputas_table',
        'create_dsp_mensajes_table',
        'create_dsp_resoluciones_table',
        'create_dsp_historial_table',
        'agrega_ubicacion_a_sol_terrenos',
        'agrega_ubicacion_a_sol_solicitudes_servicio',
        'retira_zonas',
        'renombra_el_modulo_de_reclamos',
    ]);

    foreach (['rcl_historial', 'rcl_resoluciones', 'rcl_mensajes', 'rcl_reclamos', 'sol_solicitud_historial', 'sol_solicitudes_servicio', 'sol_clientes'] as $tabla) {
        DB::table($tabla)->delete();
    }

    foreach ([1 => RCL_CLIENTE, 2 => RCL_OTRO_CLIENTE] as $id => $usuario) {
        DB::table('sol_clientes')->insert(['id' => $id, 'usuario_id' => $usuario, 'tipo' => 'persona', 'razon_social' => "Cliente {$id}", 'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now()]);
    }

    app()->instance(LecturaProveedores::class, new ProveedoresFalsos(
        [
            7 => new DatosProveedor(7, RCL_TITULAR, 'tercero', 'Proveedor Siete', 'aprobado'),
            8 => new DatosProveedor(8, RCL_OTRO_PROVEEDOR, 'tercero', 'Proveedor Ocho', 'aprobado'),
        ],
        [71 => new DatosPiloto(71, 7, RCL_PILOTO, 'Piloto Pedro', 'aprobado')],
    ));

    app()->instance(LecturaIntegrantesDeEquipo::class, new class implements LecturaIntegrantesDeEquipo
    {
        public function integrantes(int $equipoId): ?DatosIntegrantesEquipo
        {
            return new DatosIntegrantesEquipo($equipoId, 7, 71, 701, 'externo');
        }
    });
}

function rclSolicitud(string $estado, ?Carbon $enElEstadoDesde = null, ?string $comision = '1800.00'): int
{
    $id = DB::table('sol_solicitudes_servicio')->insertGetId([
        'uuid_cliente' => (string) Str::uuid(), 'cliente_id' => 1, 'terreno_id' => 1, 'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '100.0000', 'producto_indicado' => 'Glifosato', 'aporta_producto' => 'cliente',
        'ventana_desde' => '2026-12-01', 'ventana_hasta' => '2026-12-10', 'urgente' => false,
        'monto_estimado' => '12000.00', 'estado' => $estado, 'proveedor_id' => 7, 'equipo_id' => 70,
        'monto_final' => in_array($estado, ['finalizada', 'calificada'], true) ? '12000.00' : null,
        'precio_ha_acordado' => '120.0000',
        'finalizada_en' => in_array($estado, ['finalizada', 'calificada'], true) ? ($enElEstadoDesde ?? Carbon::now()->subHours(2)) : null,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('sol_solicitud_historial')->insert([
        'solicitud_id' => $id, 'estado_nuevo' => $estado, 'origen' => 'sistema',
        'created_at' => $enElEstadoDesde ?? Carbon::now()->subHours(2), 'updated_at' => now(),
    ]);

    return $id;
}

function rclEstadoDeSolicitud(int $id): string
{
    return (string) DB::table('sol_solicitudes_servicio')->where('id', $id)->value('estado');
}

function rclAbrirComoCliente(int $solicitud, string $motivo = 'cobertura_incompleta', ?string $monto = null): Reclamo
{
    return app(AbrirReclamo::class)->ejecutar(RCL_CLIENTE, ParteDeReclamo::Cliente, $solicitud, $motivo, 'Quedaron franjas sin cubrir', $monto);
}

// --- Dominio puro ---------------------------------------------------------

test('cada resultado admite solo los reembolsos que le corresponden', function (ResultadoReclamo $resultado, string $reembolso, ?string $error) {
    expect(ReglaDeResolucion::error($resultado, '12000.00', $reembolso))->toBe($error);
})->with([
    'a favor del proveedor sin reembolso' => [ResultadoReclamo::AFavorProveedor, '0.00', null],
    'a favor del proveedor con reembolso' => [ResultadoReclamo::AFavorProveedor, '100.00', 'sin_reembolso'],
    'sin lugar sin reembolso' => [ResultadoReclamo::SinLugar, '0.00', null],
    'sin lugar con reembolso' => [ResultadoReclamo::SinLugar, '1.00', 'sin_reembolso'],
    'a favor del cliente total' => [ResultadoReclamo::AFavorCliente, '12000.00', null],
    'a favor del cliente parte' => [ResultadoReclamo::AFavorCliente, '4000.00', null],
    'a favor del cliente sin monto' => [ResultadoReclamo::AFavorCliente, '0.00', 'requiere_reembolso'],
    'a favor del cliente de más' => [ResultadoReclamo::AFavorCliente, '12000.01', 'excede_monto'],
    'parcial con monto' => [ResultadoReclamo::Parcial, '3000.00', null],
    'parcial sin monto' => [ResultadoReclamo::Parcial, '0.00', 'requiere_reembolso'],
    'parcial por el total' => [ResultadoReclamo::Parcial, '12000.00', 'parcial_no_total'],
    'reembolso negativo' => [ResultadoReclamo::Parcial, '-1.00', 'negativo'],
]);

test('solo un reembolso del monto completo a favor del cliente cancela, y la comisión se ajusta en proporción', function () {
    expect(ReglaDeResolucion::esReembolsoTotal(ResultadoReclamo::AFavorCliente, '12000.00', '12000.00'))->toBeTrue()
        ->and(ReglaDeResolucion::esReembolsoTotal(ResultadoReclamo::AFavorCliente, '12000.00', '11999.99'))->toBeFalse()
        ->and(ReglaDeResolucion::esReembolsoTotal(ResultadoReclamo::Parcial, '12000.00', '12000.00'))->toBeFalse()
        ->and(ReglaDeResolucion::ajusteDeComision('1800.00', '12000.00', '3000.00'))->toBe('450.00')
        ->and(ReglaDeResolucion::ajusteDeComision('1800.00', '12000.00', '12000.00'))->toBe('1800.00')
        ->and(ReglaDeResolucion::ajusteDeComision('0.00', '12000.00', '3000.00'))->toBe('0.00')
        ->and(ReglaDeResolucion::ajusteDeComision(null, '12000.00', '3000.00'))->toBe('0.00')
        ->and(ReglaDeResolucion::ajusteDeComision('1800.00', null, '3000.00'))->toBe('0.00')
        ->and(ReglaDeResolucion::ajusteDeComision('10.00', '33.33', '11.11'))->toBe('3.33');
});

test('la tabla de transiciones del reclamo solo admite el recorrido de la especificación', function () {
    expect(TransicionesReclamo::permitida(EstadoReclamo::Abierta, EstadoReclamo::EnRevision))->toBeTrue()
        ->and(TransicionesReclamo::permitida(EstadoReclamo::Abierta, EstadoReclamo::Retirada))->toBeTrue()
        ->and(TransicionesReclamo::permitida(EstadoReclamo::EnRevision, EstadoReclamo::Resuelta))->toBeTrue()
        ->and(TransicionesReclamo::permitida(EstadoReclamo::Resuelta, EstadoReclamo::EnRevision))->toBeTrue()
        ->and(TransicionesReclamo::permitida(EstadoReclamo::Abierta, EstadoReclamo::Resuelta))->toBeFalse()
        ->and(TransicionesReclamo::permitida(EstadoReclamo::Retirada, EstadoReclamo::Abierta))->toBeFalse()
        ->and(TransicionesReclamo::destinosDesde(EstadoReclamo::Retirada))->toBe([]);
});

test('los plazos de la política se miden desde el momento correcto', function () {
    $ejecutada = new DateTimeImmutable('2026-12-01 10:00:00');

    expect(PlazosDeReclamo::clientePuedeAbrir($ejecutada, new DateTimeImmutable('2026-12-04 10:00:00'), 72))->toBeTrue()
        ->and(PlazosDeReclamo::clientePuedeAbrir($ejecutada, new DateTimeImmutable('2026-12-04 10:00:01'), 72))->toBeFalse()
        ->and(PlazosDeReclamo::proveedorPuedeAbrir(null, new DateTimeImmutable('2030-01-01'), 30))->toBeTrue()
        ->and(PlazosDeReclamo::proveedorPuedeAbrir($ejecutada, new DateTimeImmutable('2027-01-01 10:00:00'), 30))->toBeFalse()
        ->and(PlazosDeReclamo::respondeAntesDe($ejecutada, 48)->format('Y-m-d H:i'))->toBe('2026-12-03 10:00')
        ->and(PlazosDeReclamo::apelableHasta($ejecutada, 7)->format('Y-m-d'))->toBe('2026-12-08');
});

// --- Apertura -------------------------------------------------------------

test('el cliente abre un reclamo sobre un servicio ejecutado: la solicitud pasa a en reclamo y se avisa a la contraparte', function () {
    Event::fake([ReclamoAbierto::class]);
    rclMundo();
    $solicitud = rclSolicitud('finalizada');

    $reclamo = rclAbrirComoCliente($solicitud);

    expect($reclamo->estado)->toBe(EstadoReclamo::Abierta)
        ->and($reclamo->monto_en_reclamo)->toBe('12000.00')
        ->and($reclamo->movio_solicitud)->toBeTrue()
        ->and($reclamo->proveedor_id)->toBe(7)
        ->and($reclamo->responde_antes_de)->not->toBeNull()
        ->and(rclEstadoDeSolicitud($solicitud))->toBe('en_reclamo')
        ->and(DB::table('rcl_historial')->where('reclamo_id', $reclamo->id)->count())->toBe(1)
        ->and(DB::table('sol_solicitud_historial')->where('solicitud_id', $solicitud)->orderByDesc('id')->value('origen'))->toBe('cliente');
    Event::assertDispatched(ReclamoAbierto::class, fn (ReclamoAbierto $e): bool => $e->abiertaPor === 'cliente' && $e->usuariosContraparte === [RCL_TITULAR, RCL_PILOTO]);
});

test('el monto en reclamo no pasa del monto del servicio', function () {
    rclMundo();
    $solicitud = rclSolicitud('finalizada');

    expect(fn () => rclAbrirComoCliente($solicitud, 'cobertura_incompleta', '12000.01'))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.monto_invalido')
        ->and(fn () => rclAbrirComoCliente($solicitud, 'cobertura_incompleta', '0'))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.monto_invalido')
        ->and(rclAbrirComoCliente($solicitud, 'cobertura_incompleta', '3000.5')->monto_en_reclamo)->toBe('3000.50');
});

test('hay un solo reclamo por solicitud', function () {
    rclMundo();
    $solicitud = rclSolicitud('finalizada');
    rclAbrirComoCliente($solicitud);

    try {
        rclAbrirComoCliente($solicitud);
        $this->fail('debió rechazarse');
    } catch (ReclamoInvalido $excepcion) {
        expect($excepcion->esConflicto)->toBeTrue();
    }

    expect(Reclamo::query()->count())->toBe(1);
});

test('el cliente solo alega los motivos suyos y solo sobre su servicio ejecutado dentro del plazo', function () {
    rclMundo();
    $enEjecucion = rclSolicitud('en_ejecucion');
    $vencida = rclSolicitud('finalizada', Carbon::now()->subHours(73));
    $buena = rclSolicitud('finalizada');

    expect(fn () => rclAbrirComoCliente($buena, 'cobro_pendiente'))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.motivo_invalido')
        ->and(fn () => rclAbrirComoCliente($enEjecucion))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.servicio_no_declamable')
        ->and(fn () => rclAbrirComoCliente($vencida))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.plazo_vencido')
        ->and(fn () => app(AbrirReclamo::class)->ejecutar(RCL_CLIENTE, ParteDeReclamo::Cliente, $buena, 'cobertura_incompleta', '  '))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.texto_requerido')
        ->and(Reclamo::query()->count())->toBe(0)
        ->and(rclEstadoDeSolicitud($buena))->toBe('finalizada');
});

test('el servicio de otro cliente responde como si no existiera', function () {
    rclMundo();
    $solicitud = rclSolicitud('finalizada');

    try {
        app(AbrirReclamo::class)->ejecutar(RCL_OTRO_CLIENTE, ParteDeReclamo::Cliente, $solicitud, 'cobertura_incompleta', 'Detalle');
        $this->fail('debió rechazarse');
    } catch (ReclamoInvalido $excepcion) {
        expect($excepcion->esAjeno)->toBeTrue();
    }
});

test('el proveedor reclama un cobro pendiente sobre un servicio calificado y la solicitud pasa a en_reclamo', function () {
    Event::fake([ReclamoAbierto::class]);
    rclMundo();
    $solicitud = rclSolicitud('calificada');

    $reclamo = app(AbrirReclamo::class)->ejecutar(RCL_PILOTO, ParteDeReclamo::Proveedor, $solicitud, 'cobro_pendiente', 'El cliente no pagó');

    expect($reclamo->abierta_por_rol)->toBe(ParteDeReclamo::Proveedor)
        ->and($reclamo->movio_solicitud)->toBeTrue()
        ->and(rclEstadoDeSolicitud($solicitud))->toBe('en_reclamo');
    Event::assertDispatched(ReclamoAbierto::class, fn (ReclamoAbierto $e): bool => $e->usuariosContraparte === [RCL_CLIENTE]);
});

test('el proveedor que reclamo un servicio ejecutado lo pasa a en reclamo', function () {
    rclMundo();
    $solicitud = rclSolicitud('finalizada');

    $reclamo = app(AbrirReclamo::class)->ejecutar(RCL_TITULAR, ParteDeReclamo::Proveedor, $solicitud, 'cobro_pendiente', 'El cliente no paga');

    expect($reclamo->movio_solicitud)->toBeTrue()->and(rclEstadoDeSolicitud($solicitud))->toBe('en_reclamo');
});

test('el proveedor solo alega sus motivos, en los estados que corresponden y dentro de su ventana', function () {
    rclMundo();
    $enCamino = rclSolicitud('adjudicada');
    $ejecutada = rclSolicitud('finalizada');
    $enMarcha = rclSolicitud('en_ejecucion');
    $antigua = rclSolicitud('calificada', Carbon::now()->subDays(31));

    expect(app(AbrirReclamo::class)->ejecutar(RCL_TITULAR, ParteDeReclamo::Proveedor, $enCamino, 'acceso_negado', 'Portón cerrado')->movio_solicitud)->toBeFalse()
        ->and(fn () => app(AbrirReclamo::class)->ejecutar(RCL_TITULAR, ParteDeReclamo::Proveedor, $enMarcha, 'calificacion_abusiva', 'Insultos'))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.servicio_no_declamable')
        ->and(fn () => app(AbrirReclamo::class)->ejecutar(RCL_TITULAR, ParteDeReclamo::Proveedor, $ejecutada, 'cobertura_incompleta', 'No es mío'))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.motivo_invalido')
        ->and(fn () => app(AbrirReclamo::class)->ejecutar(RCL_TITULAR, ParteDeReclamo::Proveedor, $antigua, 'cobro_pendiente', 'Hace mucho'))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.plazo_vencido');
});

test('un proveedor que no prestó el servicio no abre el reclamo', function () {
    rclMundo();
    $solicitud = rclSolicitud('calificada');

    try {
        app(AbrirReclamo::class)->ejecutar(RCL_OTRO_PROVEEDOR, ParteDeReclamo::Proveedor, $solicitud, 'cobro_pendiente', 'No fue mío');
        $this->fail('debió rechazarse');
    } catch (ReclamoInvalido $excepcion) {
        expect($excepcion->esAjeno)->toBeTrue();
    }
});

// --- Mensajes y mediación -------------------------------------------------

test('las partes y soporte escriben mientras el reclamo está viva, y nadie ajeno entra', function () {
    rclMundo();
    $reclamo = rclAbrirComoCliente(rclSolicitud('finalizada'));

    app(AgregarMensajeDeReclamo::class)->comoParte(RCL_PILOTO, ParteDeReclamo::Proveedor, $reclamo->id, 'El vuelo cubrió todo');
    app(AgregarMensajeDeReclamo::class)->comoSoporte(RCL_ADMIN, $reclamo->id, '¿Tienen el registro del dron?', true);

    expect(DB::table('rcl_mensajes')->where('reclamo_id', $reclamo->id)->orderBy('id')->get(['autor_rol', 'tipo'])->map(fn ($m): string => $m->autor_rol.':'.$m->tipo)->all())->toBe(['proveedor:mensaje', 'soporte:informacion']);

    try {
        app(AgregarMensajeDeReclamo::class)->comoParte(RCL_OTRO_PROVEEDOR, ParteDeReclamo::Proveedor, $reclamo->id, 'Me meto');
        $this->fail('debió rechazarse');
    } catch (ReclamoInvalido $excepcion) {
        expect($excepcion->esAjeno)->toBeTrue();
    }

    expect(fn () => app(AgregarMensajeDeReclamo::class)->comoParte(RCL_CLIENTE, ParteDeReclamo::Cliente, $reclamo->id, ' '))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.texto_requerido');
});

test('soporte toma el reclamo una sola vez y queda registrado quién lo atiende', function () {
    rclMundo();
    $reclamo = rclAbrirComoCliente(rclSolicitud('finalizada'));

    $tomada = app(TomarReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id);
    $otraVez = app(TomarReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id);

    expect($tomada->estado)->toBe(EstadoReclamo::EnRevision)
        ->and($tomada->atendida_por)->toBe(RCL_ADMIN)
        ->and($otraVez->estado)->toBe(EstadoReclamo::EnRevision)
        ->and(DB::table('rcl_historial')->where('reclamo_id', $reclamo->id)->count())->toBe(2);
});

// --- Resolución -----------------------------------------------------------

test('un reembolso parcial devuelve la solicitud a finalizada con el reembolso registrado', function () {
    Event::fake([ReclamoResuelto::class]);
    rclMundo();
    $solicitud = rclSolicitud('finalizada');
    $reclamo = rclAbrirComoCliente($solicitud);

    $resuelta = app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id, ResultadoReclamo::Parcial, 'Faltó cubrir la franja norte', '3000');

    $resolucion = DB::table('rcl_resoluciones')->where('reclamo_id', $reclamo->id)->first();
    expect($resuelta->estado)->toBe(EstadoReclamo::Resuelta)
        ->and($resuelta->apelable_hasta)->not->toBeNull()
        ->and(rclEstadoDeSolicitud($solicitud))->toBe('finalizada')
        ->and($resolucion->monto_reembolso)->toEqual('3000.00')
        ->and($resolucion->resuelta_por)->toBe(RCL_ADMIN)
        ->and(DB::table('rcl_historial')->where('reclamo_id', $reclamo->id)->pluck('estado_nuevo')->all())->toBe(['abierta', 'en_revision', 'resuelta']);
    Event::assertDispatched(ReclamoResuelto::class, fn (ReclamoResuelto $e): bool => $e->resultado === 'parcial' && $e->montoReembolso === '3000.00' && $e->usuariosDestinatarios === [RCL_CLIENTE, RCL_TITULAR, RCL_PILOTO]);
});

test('un reembolso total a favor del cliente cancela la solicitud sin comisión que ajustar', function () {
    rclMundo();
    $solicitud = rclSolicitud('finalizada');
    $reclamo = rclAbrirComoCliente($solicitud, 'equipo_ausente');

    app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id, ResultadoReclamo::AFavorCliente, 'El equipo nunca se presentó', '12000.00', malaFeCliente: true);

    $resolucion = DB::table('rcl_resoluciones')->where('reclamo_id', $reclamo->id)->first();
    expect(rclEstadoDeSolicitud($solicitud))->toBe('cancelada')
        ->and($resolucion->comision_ajuste)->toEqual('0.00')
        ->and((bool) $resolucion->mala_fe_cliente)->toBeFalse();
});

test('a favor del proveedor o sin lugar completa la solicitud sin reembolso, y la mala fe solo se registra sin lugar', function () {
    rclMundo();
    $primera = rclSolicitud('finalizada');
    $segunda = rclSolicitud('finalizada');
    $a = rclAbrirComoCliente($primera);
    $b = rclAbrirComoCliente($segunda, 'trato_inadecuado');

    app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $a->id, ResultadoReclamo::AFavorProveedor, 'El registro prueba la cobertura', '0', malaFeCliente: true);
    app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $b->id, ResultadoReclamo::SinLugar, 'No hay prueba del reclamo', '', malaFeCliente: true);

    expect(rclEstadoDeSolicitud($primera))->toBe('finalizada')
        ->and(rclEstadoDeSolicitud($segunda))->toBe('finalizada')
        ->and((bool) DB::table('rcl_resoluciones')->where('reclamo_id', $a->id)->value('mala_fe_cliente'))->toBeFalse()
        ->and((bool) DB::table('rcl_resoluciones')->where('reclamo_id', $b->id)->value('mala_fe_cliente'))->toBeTrue()
        ->and(DB::table('rcl_resoluciones')->where('reclamo_id', $b->id)->value('monto_reembolso'))->toEqual('0.00');
});

test('un daño probado se deriva al seguro del proveedor', function () {
    rclMundo();
    $reclamo = rclAbrirComoCliente(rclSolicitud('finalizada'), 'dano_a_cultivos_o_terceros');

    app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id, ResultadoReclamo::AFavorCliente, 'Daño comprobado en el lote vecino', '5000', derivadaASeguro: true);

    expect((bool) DB::table('rcl_resoluciones')->where('reclamo_id', $reclamo->id)->value('derivada_a_seguro'))->toBeTrue();
});

test('resolver exige un reembolso que corresponda al resultado y un motivo, y un reclamo se resuelve una sola vez por ronda', function () {
    rclMundo();
    $solicitud = rclSolicitud('finalizada');
    $reclamo = rclAbrirComoCliente($solicitud);

    expect(fn () => app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id, ResultadoReclamo::SinLugar, 'Motivo', '100'))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.reembolso.sin_reembolso')
        ->and(fn () => app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id, ResultadoReclamo::AFavorCliente, 'Motivo', '13000'))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.reembolso.excede_monto')
        ->and(fn () => app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id, ResultadoReclamo::SinLugar, ' ', '0'))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.motivo_requerido')
        ->and(rclEstadoDeSolicitud($solicitud))->toBe('en_reclamo')
        ->and(Reclamo::query()->find($reclamo->id)->estado)->toBe(EstadoReclamo::Abierta);

    app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id, ResultadoReclamo::SinLugar, 'No procede', '0');

    try {
        app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id, ResultadoReclamo::AFavorCliente, 'Cambié de idea', '12000');
        $this->fail('debió rechazarse');
    } catch (ReclamoInvalido $excepcion) {
        expect($excepcion->esConflicto)->toBeTrue();
    }

    expect(DB::table('rcl_resoluciones')->where('reclamo_id', $reclamo->id)->count())->toBe(1)
        ->and(rclEstadoDeSolicitud($solicitud))->toBe('finalizada');
});

test('un reclamo del proveedor sobre un servicio calificado se resuelve y la solicitud vuelve a calificada', function () {
    rclMundo();
    $solicitud = rclSolicitud('calificada');
    $reclamo = app(AbrirReclamo::class)->ejecutar(RCL_TITULAR, ParteDeReclamo::Proveedor, $solicitud, 'cobro_pendiente', 'No pagó');

    app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id, ResultadoReclamo::AFavorProveedor, 'El cliente debe pagar', '0');

    expect(rclEstadoDeSolicitud($solicitud))->toBe('calificada')
        ->and(Reclamo::query()->find($reclamo->id)->estado)->toBe(EstadoReclamo::Resuelta);
});

// --- Retiro y apelación ---------------------------------------------------

test('quien abrió el reclamo lo retira y la solicitud se completa; es idempotente', function () {
    Event::fake([ReclamoResuelto::class]);
    rclMundo();
    $solicitud = rclSolicitud('finalizada');
    $reclamo = rclAbrirComoCliente($solicitud);

    $retirada = app(RetirarReclamo::class)->ejecutar(RCL_CLIENTE, ParteDeReclamo::Cliente, $reclamo->id);
    app(RetirarReclamo::class)->ejecutar(RCL_CLIENTE, ParteDeReclamo::Cliente, $reclamo->id);

    expect($retirada->estado)->toBe(EstadoReclamo::Retirada)
        ->and(rclEstadoDeSolicitud($solicitud))->toBe('finalizada');
    Event::assertDispatchedTimes(ReclamoResuelto::class, 1);
    Event::assertDispatched(ReclamoResuelto::class, fn (ReclamoResuelto $e): bool => $e->resultado === 'retirada');
});

test('la contraparte no retira un reclamo ajeno y uno resuelto no se retira', function () {
    rclMundo();
    $reclamo = rclAbrirComoCliente(rclSolicitud('finalizada'));

    expect(fn () => app(RetirarReclamo::class)->ejecutar(RCL_TITULAR, ParteDeReclamo::Proveedor, $reclamo->id))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.solo_quien_la_abrio');

    app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id, ResultadoReclamo::SinLugar, 'No procede', '0');

    expect(fn () => app(RetirarReclamo::class)->ejecutar(RCL_CLIENTE, ParteDeReclamo::Cliente, $reclamo->id))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.no_se_retira');
});

test('las partes apelan una sola vez dentro de siete días y la segunda resolución no mueve la solicitud', function () {
    Event::fake([ReclamoApelado::class]);
    rclMundo();
    $solicitud = rclSolicitud('finalizada');
    $reclamo = rclAbrirComoCliente($solicitud);
    app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id, ResultadoReclamo::SinLugar, 'No procede', '0');

    $apelada = app(ApelarResolucion::class)->ejecutar(RCL_CLIENTE, ParteDeReclamo::Cliente, $reclamo->id, 'El registro del dron muestra la franja sin cubrir');

    expect($apelada->estado)->toBe(EstadoReclamo::EnRevision)
        ->and($apelada->ronda)->toBe(2)
        ->and($apelada->apelable_hasta)->toBeNull()
        ->and(DB::table('rcl_mensajes')->where('reclamo_id', $reclamo->id)->value('tipo'))->toBe('apelacion');
    Event::assertDispatched(ReclamoApelado::class, fn (ReclamoApelado $e): bool => $e->apeladaPor === 'cliente' && $e->usuariosContraparte === [RCL_TITULAR, RCL_PILOTO]);

    app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id, ResultadoReclamo::Parcial, 'La prueba nueva lo justifica', '2000');

    expect(DB::table('rcl_resoluciones')->where('reclamo_id', $reclamo->id)->orderBy('ronda')->pluck('resultado')->all())->toBe(['sin_lugar', 'parcial'])
        ->and(DB::table('rcl_resoluciones')->where('reclamo_id', $reclamo->id)->where('ronda', 2)->value('comision_ajuste'))->toEqual('0.00')
        ->and(rclEstadoDeSolicitud($solicitud))->toBe('finalizada')
        ->and(Reclamo::query()->find($reclamo->id)->apelable_hasta)->toBeNull()
        ->and(fn () => app(ApelarResolucion::class)->ejecutar(RCL_TITULAR, ParteDeReclamo::Proveedor, $reclamo->id, 'Apelo yo también'))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.no_se_apela');
});

test('no se apela fuera de plazo, sin pruebas ni un reclamo que no está resuelto', function () {
    rclMundo();
    $reclamo = rclAbrirComoCliente(rclSolicitud('finalizada'));

    expect(fn () => app(ApelarResolucion::class)->ejecutar(RCL_CLIENTE, ParteDeReclamo::Cliente, $reclamo->id, 'Pruebas'))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.no_se_apela');

    app(ResolverReclamo::class)->ejecutar(RCL_ADMIN, $reclamo->id, ResultadoReclamo::SinLugar, 'No procede', '0');

    expect(fn () => app(ApelarResolucion::class)->ejecutar(RCL_CLIENTE, ParteDeReclamo::Cliente, $reclamo->id, ' '))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.texto_requerido');

    Reclamo::query()->whereKey($reclamo->id)->update(['apelable_hasta' => Carbon::now()->subMinute()]);

    expect(fn () => app(ApelarResolucion::class)->ejecutar(RCL_CLIENTE, ParteDeReclamo::Cliente, $reclamo->id, 'Pruebas'))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.apelacion_vencida');
});

// --- Lo que ve cada lado --------------------------------------------------

test('cada lado ve solo sus reclamos y una ajena responde como si no existiera', function () {
    rclMundo();
    $propia = rclAbrirComoCliente(rclSolicitud('finalizada'));
    $consulta = app(ConsultarReclamos::class);

    expect($consulta->propias(RCL_CLIENTE, ParteDeReclamo::Cliente)->total())->toBe(1)
        ->and($consulta->propias(RCL_OTRO_CLIENTE, ParteDeReclamo::Cliente)->total())->toBe(0)
        ->and($consulta->propias(RCL_PILOTO, ParteDeReclamo::Proveedor)->total())->toBe(1)
        ->and($consulta->propias(RCL_OTRO_PROVEEDOR, ParteDeReclamo::Proveedor)->total())->toBe(0)
        ->and($consulta->propias(RCL_CLIENTE, ParteDeReclamo::Cliente, 'resuelta')->total())->toBe(0)
        ->and($consulta->una(RCL_TITULAR, ParteDeReclamo::Proveedor, $propia->id)->id)->toBe($propia->id)
        ->and($consulta->deLaSolicitud(RCL_CLIENTE, ParteDeReclamo::Cliente, $propia->solicitud_id)->id)->toBe($propia->id)
        ->and(fn () => $consulta->una(RCL_OTRO_CLIENTE, ParteDeReclamo::Cliente, $propia->id))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.reclamo_ajeno')
        ->and(fn () => $consulta->una(RCL_OTRO_PROVEEDOR, ParteDeReclamo::Proveedor, $propia->id))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.reclamo_ajeno')
        ->and(fn () => $consulta->deLaSolicitud(RCL_OTRO_CLIENTE, ParteDeReclamo::Cliente, $propia->solicitud_id))->toRechazarCon(ReclamoInvalido::class, 'reclamos.errores.reclamo_ajeno');
});
