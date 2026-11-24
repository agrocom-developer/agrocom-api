<?php

use App\Dominios\Calificaciones\Aplicacion\CalificarComoCliente;
use App\Dominios\Calificaciones\Aplicacion\CalificarComoProveedor;
use App\Dominios\Calificaciones\Aplicacion\ConsultarCalificacionesDelServicio;
use App\Dominios\Calificaciones\Aplicacion\ListarCalificacionesRecibidas;
use App\Dominios\Calificaciones\Aplicacion\ListarParaModeracion;
use App\Dominios\Calificaciones\Aplicacion\ModerarCalificacion;
use App\Dominios\Calificaciones\Aplicacion\RecalcularReputacion;
use App\Dominios\Calificaciones\Aplicacion\ReportarCalificacion;
use App\Dominios\Calificaciones\Contratos\Eventos\CalificacionModerada;
use App\Dominios\Calificaciones\Contratos\Eventos\CalificacionRegistrada;
use App\Dominios\Calificaciones\Contratos\Eventos\CalificacionReportada;
use App\Dominios\Calificaciones\Contratos\Eventos\ReputacionDeProveedorActualizada;
use App\Dominios\Calificaciones\Dominio\CausaModeracion;
use App\Dominios\Calificaciones\Dominio\EstadoCalificacion;
use App\Dominios\Calificaciones\Dominio\Excepciones\CalificacionInvalida;
use App\Dominios\Calificaciones\Dominio\PlazoDeCalificacion;
use App\Dominios\Calificaciones\Dominio\PromedioDeCalificaciones;
use App\Dominios\Calificaciones\Dominio\TransicionesCalificacion;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;
use App\Dominios\Flota\Contratos\DatosIntegrantesEquipo;
use App\Dominios\Flota\Contratos\LecturaIntegrantesDeEquipo;
use App\Dominios\Proveedores\Contratos\CalificacionDeProveedores;
use App\Dominios\Proveedores\Contratos\DatosAuxiliar;
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
 * Calificación bidireccional del servicio (ADR 0031 y 0039): el cliente califica al
 * proveedor, al piloto y al auxiliar; el proveedor califica al cliente en privado;
 * el proveedor reporta y el administrador modera; la reputación se recalcula en
 * decimal exacto. Contra el ESQUEMA REAL de solicitudes y calificaciones (las
 * migraciones, en SQLite en memoria); los módulos vecinos entran por sus contratos,
 * con datos controlados por la prueba.
 *
 * Mundo: cliente 1 (cuenta 900), proveedor 7 (titular 700, piloto 71 con cuenta 710,
 * auxiliar 72), otro proveedor 8 (titular 800). La solicitud 1 está completada ayer.
 */

uses(TestCase::class);

const CAL_CLIENTE_USUARIO = 900;
const CAL_TITULAR = 700;
const CAL_PILOTO_USUARIO = 710;
const CAL_OTRO_TITULAR = 800;
const CAL_ADMIN = 5;

/** Doble de `CalificacionDeProveedores`: guarda lo último que recibió por proveedor. */
final class CalificacionesGuardadas implements CalificacionDeProveedores
{
    /** @var array<int, array{promedio: string, conteo: int}> */
    public array $porProveedor = [];

    public function actualizar(int $proveedorId, string $promedio, int $conteo): void
    {
        $this->porProveedor[$proveedorId] = ['promedio' => $promedio, 'conteo' => $conteo];
    }
}

function calMundo(bool $conAuxiliar = true): CalificacionesGuardadas
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'agrega_negociacion_a_sol_solicitudes_servicio',
        'create_sol_solicitud_historial_table',
        'agrega_reputacion_a_sol_clientes',
        'create_cal_calificaciones_table',
        'create_cal_moderaciones_table',
    ]);

    foreach (['cal_moderaciones', 'cal_calificaciones', 'sol_solicitud_historial', 'sol_solicitudes_servicio', 'sol_clientes'] as $tabla) {
        DB::table($tabla)->delete();
    }

    DB::table('sol_clientes')->insert([
        'id' => 1, 'usuario_id' => CAL_CLIENTE_USUARIO, 'tipo' => 'persona', 'razon_social' => 'Cliente Ana', 'estado' => 'activo',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    app()->instance(LecturaProveedores::class, new ProveedoresFalsos(
        [
            7 => new DatosProveedor(7, CAL_TITULAR, 'tercero', 'Proveedor Siete', 'aprobado'),
            8 => new DatosProveedor(8, CAL_OTRO_TITULAR, 'tercero', 'Proveedor Ocho', 'aprobado'),
        ],
        [71 => new DatosPiloto(71, 7, CAL_PILOTO_USUARIO, 'Piloto Pedro', 'aprobado')],
        [72 => new DatosAuxiliar(72, 7, 720, 'Auxiliar Ana', 'aprobado')],
    ));

    app()->instance(LecturaIntegrantesDeEquipo::class, new class($conAuxiliar) implements LecturaIntegrantesDeEquipo
    {
        public function __construct(private bool $conAuxiliar) {}

        public function integrantes(int $equipoId): ?DatosIntegrantesEquipo
        {
            return new DatosIntegrantesEquipo($equipoId, 7, 71, 701, $this->conAuxiliar ? 72 : null, 'externo');
        }
    });

    $guardadas = new CalificacionesGuardadas;
    app()->instance(CalificacionDeProveedores::class, $guardadas);

    return $guardadas;
}

function calCompletada(string $estado = 'finalizada', ?Carbon $completadaEn = null): int
{
    $id = DB::table('sol_solicitudes_servicio')->insertGetId([
        'uuid_cliente' => (string) Str::uuid(), 'cliente_id' => 1, 'terreno_id' => 1, 'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '100.0000', 'producto_indicado' => 'Glifosato', 'aporta_producto' => 'cliente',
        'ventana_desde' => '2026-12-01', 'ventana_hasta' => '2026-12-10', 'urgente' => false,
        'monto_estimado' => '12000.00', 'estado' => $estado, 'proveedor_id' => 7, 'equipo_id' => 70,
        'finalizada_en' => in_array($estado, ['finalizada', 'calificada'], true) ? ($completadaEn ?? Carbon::now()->subDay()) : null,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('sol_solicitud_historial')->insert([
        'solicitud_id' => $id, 'estado_nuevo' => $estado, 'origen' => 'cliente',
        'created_at' => $completadaEn ?? Carbon::now()->subDay(), 'updated_at' => now(),
    ]);

    return $id;
}

/** @return array{uuid_cliente: string, sujeto_tipo: string, estrellas: int, etiquetas: list<string>, comentario: string} */
function calItem(string $sujeto, int $estrellas, array $etiquetas = ['puntualidad'], ?string $uuid = null): array
{
    return ['uuid_cliente' => $uuid ?? (string) Str::uuid(), 'sujeto_tipo' => $sujeto, 'estrellas' => $estrellas, 'etiquetas' => $etiquetas, 'comentario' => 'Comentario '.$sujeto];
}

// --- Dominio puro ---------------------------------------------------------

test('el promedio del proveedor pondera por sujeto y el del cliente es simple', function () {
    $pesos = ['proveedor' => '0.50', 'piloto' => '0.35', 'auxiliar' => '0.15'];

    expect(PromedioDeCalificaciones::ponderado([
        ['sujeto' => 'proveedor', 'estrellas' => 5],
        ['sujeto' => 'piloto', 'estrellas' => 4],
        ['sujeto' => 'auxiliar', 'estrellas' => 3],
    ], $pesos))->toBe('4.35')
        ->and(PromedioDeCalificaciones::ponderado([['sujeto' => 'proveedor', 'estrellas' => 4], ['sujeto' => 'piloto', 'estrellas' => 5]], $pesos))->toBe('4.41')
        ->and(PromedioDeCalificaciones::ponderado([], $pesos))->toBe('0.00')
        ->and(PromedioDeCalificaciones::ponderado([['sujeto' => 'cliente', 'estrellas' => 5]], $pesos))->toBe('0.00')
        ->and(PromedioDeCalificaciones::simple([5, 4, 4]))->toBe('4.33')
        ->and(PromedioDeCalificaciones::simple([]))->toBe('0.00');
});

test('la tabla de transiciones de la calificación solo admite moderar y restablecer', function () {
    expect(TransicionesCalificacion::permitida(EstadoCalificacion::Visible, EstadoCalificacion::EnModeracion))->toBeTrue()
        ->and(TransicionesCalificacion::permitida(EstadoCalificacion::Visible, EstadoCalificacion::Oculta))->toBeTrue()
        ->and(TransicionesCalificacion::permitida(EstadoCalificacion::EnModeracion, EstadoCalificacion::Visible))->toBeTrue()
        ->and(TransicionesCalificacion::permitida(EstadoCalificacion::Oculta, EstadoCalificacion::Visible))->toBeTrue()
        ->and(TransicionesCalificacion::permitida(EstadoCalificacion::Oculta, EstadoCalificacion::EnModeracion))->toBeFalse()
        ->and(TransicionesCalificacion::permitida(EstadoCalificacion::Visible, EstadoCalificacion::Visible))->toBeFalse();
});

test('el plazo para calificar son siete días desde que se completó', function () {
    $completada = new DateTimeImmutable('2026-12-01 10:00:00');

    expect(PlazoDeCalificacion::estaAbierto($completada, new DateTimeImmutable('2026-12-08 10:00:00'), 7))->toBeTrue()
        ->and(PlazoDeCalificacion::estaAbierto($completada, new DateTimeImmutable('2026-12-08 10:00:01'), 7))->toBeFalse();
});

// --- El cliente califica --------------------------------------------------

test('el cliente califica al proveedor, al piloto y al auxiliar y la reputación del proveedor se pondera', function () {
    Event::fake([CalificacionRegistrada::class, ReputacionDeProveedorActualizada::class]);
    $guardadas = calMundo();
    $id = calCompletada();

    $resultado = app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $id, [
        calItem('proveedor', 5, ['puntualidad', 'comunicacion']),
        calItem('piloto', 4, ['cobertura_pareja']),
        calItem('auxiliar', 3, []),
    ]);

    expect($resultado)->toHaveCount(3)
        ->and(array_column($resultado, 'creada'))->toBe([true, true, true])
        ->and(Calificacion::query()->where('solicitud_id', $id)->pluck('sujeto_id', 'sujeto_tipo')->all())->toEqual(['proveedor' => 7, 'piloto' => 71, 'auxiliar' => 72])
        ->and($guardadas->porProveedor[7])->toBe(['promedio' => '4.35', 'conteo' => 1]);

    Event::assertDispatchedTimes(CalificacionRegistrada::class, 3);
    Event::assertDispatched(CalificacionRegistrada::class, fn (CalificacionRegistrada $e): bool => $e->sujetoTipo === 'proveedor' && $e->usuariosDestinatarios === [CAL_TITULAR, CAL_PILOTO_USUARIO]);
    Event::assertDispatched(ReputacionDeProveedorActualizada::class, fn (ReputacionDeProveedorActualizada $e): bool => $e->proveedorId === 7 && $e->promedio === '4.35');
});

test('reintentar con el mismo uuid devuelve lo guardado sin duplicar ni recalcular', function () {
    Event::fake([CalificacionRegistrada::class]);
    calMundo();
    $id = calCompletada();
    $item = calItem('proveedor', 5);

    $primera = app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $id, [$item]);
    $segunda = app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $id, [$item]);

    expect($primera[0]['creada'])->toBeTrue()
        ->and($segunda[0]['creada'])->toBeFalse()
        ->and($segunda[0]['calificacion']->id)->toBe($primera[0]['calificacion']->id)
        ->and(Calificacion::query()->count())->toBe(1);
    Event::assertDispatchedTimes(CalificacionRegistrada::class, 1);
});

test('una calificación no se edita: calificar de nuevo al mismo sujeto es un conflicto', function () {
    calMundo();
    $id = calCompletada();
    app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $id, [calItem('proveedor', 5)]);

    try {
        app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $id, [calItem('proveedor', 1)]);
        $this->fail('debió rechazarse');
    } catch (CalificacionInvalida $excepcion) {
        expect($excepcion->esConflicto)->toBeTrue();
    }

    expect(Calificacion::query()->where('sujeto_tipo', 'proveedor')->value('estrellas'))->toBe(5);
});

test('un uuid ya usado en otra calificación se rechaza', function () {
    calMundo();
    $id = calCompletada();
    $uuid = (string) Str::uuid();
    app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $id, [calItem('proveedor', 5, [], $uuid)]);

    expect(fn () => app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $id, [calItem('piloto', 4, [], $uuid)]))
        ->toRechazarCon(CalificacionInvalida::class, 'calificaciones.errores.uuid_en_uso');
});

test('solo se califica un servicio completado y dentro del plazo', function () {
    calMundo();
    $ejecutada = calCompletada('en_ejecucion');
    $vencida = calCompletada('finalizada', Carbon::now()->subDays(8));
    $justo = calCompletada('finalizada', Carbon::now()->subDays(6));

    expect(fn () => app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $ejecutada, [calItem('proveedor', 5)]))->toRechazarCon(CalificacionInvalida::class, 'calificaciones.errores.servicio_no_completado')
        ->and(fn () => app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $vencida, [calItem('proveedor', 5)]))->toRechazarCon(CalificacionInvalida::class, 'calificaciones.errores.plazo_vencido')
        ->and(app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $justo, [calItem('proveedor', 5)]))->toHaveCount(1);
});

test('un servicio de otro cliente responde como si no existiera', function () {
    calMundo();
    $id = calCompletada();

    try {
        app(CalificarComoCliente::class)->ejecutar(999, $id, [calItem('proveedor', 5)]);
        $this->fail('debió rechazarse');
    } catch (CalificacionInvalida $excepcion) {
        expect($excepcion->esAjeno)->toBeTrue();
    }

    expect(fn () => app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, 12345, [calItem('proveedor', 5)]))->toThrow(CalificacionInvalida::class);
});

test('no se califica a un auxiliar que el equipo no llevaba ni con etiquetas de otro lado', function () {
    calMundo(conAuxiliar: false);
    $id = calCompletada();

    expect(fn () => app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $id, [calItem('auxiliar', 4)]))->toRechazarCon(CalificacionInvalida::class, 'calificaciones.errores.sujeto_invalido')
        ->and(fn () => app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $id, [calItem('proveedor', 4, ['acceso_lote'])]))->toRechazarCon(CalificacionInvalida::class, 'calificaciones.errores.etiqueta_invalida')
        ->and(Calificacion::query()->count())->toBe(0);
});

test('una llamada con una calificación inválida no guarda ninguna de las demás', function () {
    calMundo(conAuxiliar: false);
    $id = calCompletada();

    expect(fn () => app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $id, [calItem('proveedor', 5), calItem('auxiliar', 4)]))->toThrow(CalificacionInvalida::class)
        ->and(Calificacion::query()->count())->toBe(0);
});

test('el promedio del proveedor toma las últimas calificaciones de la ventana', function () {
    config(['calificaciones.ventana' => 2]);
    $guardadas = calMundo();

    foreach ([1, 5, 5] as $estrellas) {
        app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, calCompletada(), [calItem('proveedor', $estrellas)]);
    }

    expect($guardadas->porProveedor[7])->toBe(['promedio' => '5.00', 'conteo' => 2]);
});

// --- El proveedor califica al cliente ------------------------------------

test('el proveedor califica al cliente en privado y la reputación del cliente se actualiza', function () {
    Event::fake([CalificacionRegistrada::class]);
    calMundo();
    $id = calCompletada();

    $resultado = app(CalificarComoProveedor::class)->ejecutar(CAL_PILOTO_USUARIO, $id, ['uuid_cliente' => (string) Str::uuid(), 'estrellas' => 2, 'etiquetas' => ['acceso_lote'], 'comentario' => 'No había acceso']);

    expect($resultado['creada'])->toBeTrue()
        ->and($resultado['calificacion']->sujeto_tipo->value)->toBe('cliente')
        ->and(DB::table('sol_clientes')->find(1)->calificacion_promedio)->toEqual('2.00')
        ->and(DB::table('sol_clientes')->find(1)->calificaciones_conteo)->toBe(1);
    Event::assertDispatched(CalificacionRegistrada::class, fn (CalificacionRegistrada $e): bool => $e->usuariosDestinatarios === [] && $e->sujetoTipo === 'cliente');
});

test('un proveedor que no prestó el servicio no califica al cliente', function () {
    calMundo();
    $id = calCompletada();

    try {
        app(CalificarComoProveedor::class)->ejecutar(CAL_OTRO_TITULAR, $id, ['uuid_cliente' => (string) Str::uuid(), 'estrellas' => 1]);
        $this->fail('debió rechazarse');
    } catch (CalificacionInvalida $excepcion) {
        expect($excepcion->esAjeno)->toBeTrue();
    }
});

test('un promedio bajo del cliente con diez calificaciones le avisa y el proveedor no ve la calificación del cliente', function () {
    Event::fake([CalificacionRegistrada::class]);
    calMundo();

    for ($i = 0; $i < 10; $i++) {
        app(CalificarComoProveedor::class)->ejecutar(CAL_TITULAR, calCompletada(), ['uuid_cliente' => (string) Str::uuid(), 'estrellas' => 3]);
    }

    expect(DB::table('sol_clientes')->find(1)->calificacion_promedio)->toEqual('3.00')
        ->and(DB::table('sol_clientes')->find(1)->aviso_calificacion_en)->not->toBeNull()
        ->and(app(ListarCalificacionesRecibidas::class)->ejecutar(CAL_TITULAR)['pagina']->total())->toBe(0);
});

// --- Reporte y moderación -------------------------------------------------

test('el proveedor reporta una calificación suya, sigue contando y un administrador la oculta con causa', function () {
    Event::fake([CalificacionReportada::class, CalificacionModerada::class]);
    $guardadas = calMundo();
    $id = calCompletada();
    $resultado = app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $id, [calItem('proveedor', 1)]);
    $calificacionId = $resultado[0]['calificacion']->id;

    $reportada = app(ReportarCalificacion::class)->ejecutar(CAL_PILOTO_USUARIO, $calificacionId, 'Insultos en el comentario');
    app(ReportarCalificacion::class)->ejecutar(CAL_PILOTO_USUARIO, $calificacionId, 'Insultos en el comentario');

    expect($reportada->estado)->toBe(EstadoCalificacion::EnModeracion)
        ->and($guardadas->porProveedor[7]['promedio'])->toBe('1.00');
    Event::assertDispatchedTimes(CalificacionReportada::class, 1);

    $oculta = app(ModerarCalificacion::class)->ocultar(CAL_ADMIN, $calificacionId, CausaModeracion::InsultosODatosPersonales, 'Contiene insultos');

    expect($oculta->estado)->toBe(EstadoCalificacion::Oculta)
        ->and($oculta->moderada_por)->toBe(CAL_ADMIN)
        ->and($oculta->causa_moderacion)->toBe(CausaModeracion::InsultosODatosPersonales)
        ->and($oculta->estrellas)->toBe(1)
        ->and($oculta->comentario)->toBe('Comentario proveedor')
        ->and($guardadas->porProveedor[7])->toBe(['promedio' => '0.00', 'conteo' => 0])
        ->and(DB::table('cal_moderaciones')->where('calificacion_id', $calificacionId)->orderBy('id')->pluck('estado_nuevo')->all())->toBe(['en_moderacion', 'oculta']);
    Event::assertDispatched(CalificacionModerada::class, fn (CalificacionModerada $e): bool => $e->estadoNuevo === 'oculta' && $e->causa === 'insultos_o_datos_personales');
});

test('moderar es idempotente y restablecer devuelve la calificación al promedio', function () {
    Event::fake([CalificacionModerada::class]);
    $guardadas = calMundo();
    $resultado = app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, calCompletada(), [calItem('proveedor', 2)]);
    $id = $resultado[0]['calificacion']->id;

    app(ModerarCalificacion::class)->ocultar(CAL_ADMIN, $id, CausaModeracion::CausaAjena, 'Lluvia');
    app(ModerarCalificacion::class)->ocultar(CAL_ADMIN, $id, CausaModeracion::CausaAjena, 'Lluvia');
    Event::assertDispatchedTimes(CalificacionModerada::class, 1);

    $visible = app(ModerarCalificacion::class)->dejarVisible(CAL_ADMIN, $id, 'Fue un error');

    expect($visible->estado)->toBe(EstadoCalificacion::Visible)
        ->and($guardadas->porProveedor[7])->toBe(['promedio' => '2.00', 'conteo' => 1])
        ->and(DB::table('cal_moderaciones')->where('calificacion_id', $id)->count())->toBe(2);
});

test('moderar exige motivo y nadie modera una calificación que escribió él mismo', function () {
    calMundo();
    $resultado = app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, calCompletada(), [calItem('proveedor', 2)]);
    $id = $resultado[0]['calificacion']->id;

    expect(fn () => app(ModerarCalificacion::class)->ocultar(CAL_ADMIN, $id, CausaModeracion::Otra, '  '))->toRechazarCon(CalificacionInvalida::class, 'calificaciones.errores.motivo_requerido')
        ->and(fn () => app(ModerarCalificacion::class)->ocultar(CAL_CLIENTE_USUARIO, $id, CausaModeracion::Otra, 'Me arrepentí'))->toRechazarCon(CalificacionInvalida::class, 'calificaciones.errores.propia_calificacion')
        ->and(Calificacion::query()->find($id)->estado)->toBe(EstadoCalificacion::Visible);
});

test('ocultar la calificación de un cliente recalcula la reputación del cliente', function () {
    calMundo();
    $id = calCompletada();
    $resultado = app(CalificarComoProveedor::class)->ejecutar(CAL_TITULAR, $id, ['uuid_cliente' => (string) Str::uuid(), 'estrellas' => 1]);

    app(ModerarCalificacion::class)->ocultar(CAL_ADMIN, $resultado['calificacion']->id, CausaModeracion::Represalia, 'Tras una disputa');

    expect(DB::table('sol_clientes')->find(1)->calificaciones_conteo)->toBe(0)
        ->and(DB::table('sol_clientes')->find(1)->calificacion_promedio)->toEqual('0.00');
});

test('un proveedor no reporta calificaciones ajenas ni las suyas hacia el cliente', function () {
    calMundo();
    $id = calCompletada();
    $cliente = app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $id, [calItem('proveedor', 1)])[0]['calificacion'];
    $privada = app(CalificarComoProveedor::class)->ejecutar(CAL_TITULAR, $id, ['uuid_cliente' => (string) Str::uuid(), 'estrellas' => 1])['calificacion'];

    expect(fn () => app(ReportarCalificacion::class)->ejecutar(CAL_OTRO_TITULAR, $cliente->id, 'Abusiva'))->toRechazarCon(CalificacionInvalida::class, 'calificaciones.errores.calificacion_ajena')
        ->and(fn () => app(ReportarCalificacion::class)->ejecutar(CAL_TITULAR, $privada->id, 'Abusiva'))->toRechazarCon(CalificacionInvalida::class, 'calificaciones.errores.calificacion_ajena')
        ->and(fn () => app(ReportarCalificacion::class)->ejecutar(CAL_TITULAR, $cliente->id, ' '))->toRechazarCon(CalificacionInvalida::class, 'calificaciones.errores.motivo_requerido');
});

// --- Lo que ve cada lado --------------------------------------------------

test('el proveedor ve lo que recibe, sin lo oculto, con su resumen', function () {
    calMundo();
    foreach ([5, 4, 1] as $estrellas) {
        app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, calCompletada(), [calItem('proveedor', $estrellas, ['puntualidad'])]);
    }
    $oculta = Calificacion::query()->where('estrellas', 1)->first();
    app(ModerarCalificacion::class)->ocultar(CAL_ADMIN, $oculta->id, CausaModeracion::SinServicioReal, 'Cuenta sin servicio');

    $datos = app(ListarCalificacionesRecibidas::class)->ejecutar(CAL_PILOTO_USUARIO);

    expect($datos['pagina']->total())->toBe(2)
        ->and(collect($datos['pagina']->items())->pluck('estrellas')->sort()->values()->all())->toBe([4, 5])
        ->and($datos['resumen']['por_estrellas'])->toBe([1 => 0, 2 => 0, 3 => 0, 4 => 1, 5 => 1])
        ->and($datos['resumen']['etiquetas'])->toBe(['puntualidad' => 2])
        ->and(fn () => app(ListarCalificacionesRecibidas::class)->ejecutar(12345))->toThrow(CalificacionInvalida::class);
});

test('la cola de moderación pone primero las reportadas y filtra', function () {
    calMundo();
    $primera = app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, calCompletada(), [calItem('proveedor', 5)])[0]['calificacion'];
    app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, calCompletada(), [calItem('proveedor', 4)]);
    app(ReportarCalificacion::class)->ejecutar(CAL_TITULAR, $primera->id, 'Abusiva');

    $cola = app(ListarParaModeracion::class)->ejecutar(null, null);
    $soloReportadas = app(ListarParaModeracion::class)->ejecutar('en_moderacion', 'proveedor');

    expect($cola->items()[0]->id)->toBe($primera->id)
        ->and($soloReportadas->total())->toBe(1);
});

test('el estado del servicio dice a quién falta calificar y hasta cuándo', function () {
    calMundo();
    $id = calCompletada();
    app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, $id, [calItem('proveedor', 5)]);

    $cliente = app(ConsultarCalificacionesDelServicio::class)->paraCliente(CAL_CLIENTE_USUARIO, $id);
    $proveedor = app(ConsultarCalificacionesDelServicio::class)->paraProveedor(CAL_TITULAR, $id);

    expect($cliente['puede_calificar'])->toBeTrue()
        ->and(collect($cliente['sujetos'])->pluck('tipo')->all())->toBe(['proveedor', 'piloto', 'auxiliar'])
        ->and($cliente['sujetos'][0]['calificacion']?->estrellas)->toBe(5)
        ->and($cliente['sujetos'][1]['calificacion'])->toBeNull()
        ->and($cliente['vence_en'])->not->toBeNull()
        ->and($proveedor['puede_calificar'])->toBeTrue()
        ->and($proveedor['sujetos'][0]['tipo'])->toBe('cliente')
        ->and($proveedor['sujetos'][0]['nombre'])->toBe('Cliente Ana')
        ->and($proveedor['sujetos'][0]['calificacion'])->toBeNull();

    $ejecutada = calCompletada('en_ejecucion');
    expect(app(ConsultarCalificacionesDelServicio::class)->paraCliente(CAL_CLIENTE_USUARIO, $ejecutada)['puede_calificar'])->toBeFalse();
});

test('recalcular la reputación dos veces deja el mismo resultado', function () {
    $guardadas = calMundo();
    app(CalificarComoCliente::class)->ejecutar(CAL_CLIENTE_USUARIO, calCompletada(), [calItem('proveedor', 4), calItem('piloto', 5)]);

    $primera = app(RecalcularReputacion::class)->deProveedor(7);
    $segunda = app(RecalcularReputacion::class)->deProveedor(7);

    expect($segunda)->toBe($primera)->and($guardadas->porProveedor[7])->toBe($primera);
});
