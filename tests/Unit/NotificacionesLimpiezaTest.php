<?php

use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModoSoloLectura;
use App\Dominios\Notificaciones\Aplicacion\AbrirAlerta;
use App\Dominios\Notificaciones\Aplicacion\LimpiarNotificaciones;
use App\Dominios\Notificaciones\Aplicacion\MarcarTodasLeidas;
use App\Dominios\Notificaciones\Contratos\LecturaNotificaciones;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\AlertaVista;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
 * «Marcar como leído al abrir» y «Limpiar» de la campana (seguimiento de la
 * tarea 141, ADR 0025 punto 11). Contra el ESQUEMA REAL (las migraciones, en
 * SQLite en memoria) y los casos de uso reales.
 *
 * Lo que se garantiza:
 *
 *   1. Abrir una alerta técnica la deja leída PARA ESA CUENTA — sin tocar a otra ni
 *      declarar atendida la alerta —, es idempotente y exige que la alerta exista.
 *   2. Con la vista «como otro usuario» activa (solo lectura) no se escribe nada.
 *   3. «Marcar todas» y «Limpiar» solo tocan a la cuenta que los pide: los avisos y el
 *      estado sobre las alertas de otra cuenta quedan idénticos (exposición de datos
 *      entre usuarios: categoría que CLAUDE.md pide cubrir).
 *   4. «Limpiar» da de baja (soft delete) todos los avisos de la cuenta, leídos o no,
 *      y cada baja deja su fila de bitácora (invariantes 8 y 9).
 *   5. Lo limpiado no vuelve a la campana.
 *
 * Las alertas viven en Operaciones: se reemplaza su contrato de lectura por uno que
 * conoce tres ids (10, 11 y 12), que es lo único que estos casos de uso le piden.
 */

uses(TestCase::class);

const LIMPIEZA_ALERTAS_EXISTENTES = [10, 11, 12];

function limpiezaEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_sec_user_table',
        'create_sec_user_preferencia_table',
        'create_ntf_notificaciones_table',
        'create_ntf_alertas_vistas_table',
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
            // SQLite no conoce el índice parcial de Postgres de las migraciones de `sec_*`.
            if (! str_contains($excepcion->getMessage(), 'USING btree')) {
                throw $excepcion;
            }
        }
    }
}

function limpiezaCuenta(): int
{
    static $secuencia = 0;
    $secuencia++;
    $ahora = now();

    return DB::table('sec_user')->insertGetId([
        'name' => "Cuenta {$secuencia}",
        'username' => "limpieza.{$secuencia}",
        'password' => 'no-se-usa',
        'type' => TipoUsuario::Interno->value,
        'state' => true,
        'created_at' => $ahora,
        'updated_at' => $ahora,
    ]);
}

function limpiezaAviso(int $usuarioId, string $clave, bool $leido = false): Notificacion
{
    return Notificacion::create([
        'usuario_id' => $usuarioId,
        'tipo' => TipoNotificacion::ContratoCreado,
        'clave_evento' => $clave,
        'parametros' => ['cliente' => 'Cliente', 'hectareas' => '10,00'],
        'recurso_tipo' => RecursoNotificable::Contrato,
        'recurso_id' => 1,
        'leida_en' => $leido ? now() : null,
    ]);
}

/** @return array{leidas: list<int>, limpiadas: list<int>} */
function limpiezaEstadoAlertas(int $usuarioId): array
{
    $estado = app(LecturaNotificaciones::class)->alertasDeCuenta($usuarioId);

    return ['leidas' => collect($estado->leidas)->sort()->values()->all(), 'limpiadas' => collect($estado->limpiadas)->sort()->values()->all()];
}

beforeEach(function () {
    limpiezaEsquema();

    $this->mock(LecturaPanelOperaciones::class, function ($mock): void {
        $mock->shouldReceive('idsAlertasExistentes')->andReturnUsing(
            static fn (array $ids): array => array_values(array_intersect($ids, LIMPIEZA_ALERTAS_EXISTENTES)),
        );
    });
});

afterEach(function () {
    ModoSoloLectura::desactivar();
});

// ── 1. Abrir una alerta ────────────────────────────────────────────────────────

test('abrir una alerta la deja leída solo para esa cuenta', function () {
    $ana = limpiezaCuenta();
    $beto = limpiezaCuenta();

    app(AbrirAlerta::class)->ejecutar($ana, 10);

    expect(limpiezaEstadoAlertas($ana))->toBe(['leidas' => [10], 'limpiadas' => []])
        ->and(limpiezaEstadoAlertas($beto))->toBe(['leidas' => [], 'limpiadas' => []])
        ->and(AlertaVista::query()->count())->toBe(1);
});

test('abrir la misma alerta dos veces no duplica la fila ni cambia cuándo se leyó', function () {
    $ana = limpiezaCuenta();

    app(AbrirAlerta::class)->ejecutar($ana, 10);
    $primera = AlertaVista::query()->deUsuario($ana)->firstOrFail()->leida_en;

    $this->travel(5)->minutes();
    app(AbrirAlerta::class)->ejecutar($ana, 10);

    $filas = AlertaVista::query()->deUsuario($ana)->get();

    expect($filas)->toHaveCount(1)
        ->and($filas->first()->leida_en->equalTo($primera))->toBeTrue();
});

test('una alerta que no existe no deja rastro: da 404 y no guarda nada', function () {
    $ana = limpiezaCuenta();

    expect(fn () => app(AbrirAlerta::class)->ejecutar($ana, 999))->toThrow(ModelNotFoundException::class)
        ->and(AlertaVista::withTrashed()->count())->toBe(0);
});

test('abrir una alerta deja su fila de bitácora', function () {
    $ana = limpiezaCuenta();

    app(AbrirAlerta::class)->ejecutar($ana, 10);
    $vista = AlertaVista::query()->deUsuario($ana)->firstOrFail();

    expect(Bitacora::query()->where('tabla', 'ntf_alertas_vistas')->where('registro_id', $vista->id)->where('accion', 'creado')->count())->toBe(1);
});

// ── 2. Vista «como otro usuario» ────────────────────────────────────────────────

test('con la vista como otro usuario activa abrir una alerta no escribe, pero tampoco falla', function () {
    $ana = limpiezaCuenta();
    ModoSoloLectura::activar();

    app(AbrirAlerta::class)->ejecutar($ana, 10);

    ModoSoloLectura::desactivar();

    expect(AlertaVista::withTrashed()->count())->toBe(0);
});

// ── 3. Marcar todas: solo la cuenta que lo pide ────────────────────────────────

test('marcar todas marca los avisos y las alertas de la cuenta, e ignora las que no existen', function () {
    $ana = limpiezaCuenta();
    limpiezaAviso($ana, 'contrato_creado:1');
    limpiezaAviso($ana, 'contrato_creado:2');

    $marcadas = app(MarcarTodasLeidas::class)->ejecutar($ana, [10, 11, 999]);

    expect($marcadas)->toBe(4)
        ->and(Notificacion::query()->deUsuario($ana)->sinLeer()->count())->toBe(0)
        ->and(limpiezaEstadoAlertas($ana))->toBe(['leidas' => [10, 11], 'limpiadas' => []])
        ->and(app(MarcarTodasLeidas::class)->ejecutar($ana, [10, 11]))->toBe(0);
});

test('marcar todas no toca los avisos ni las alertas de otra cuenta', function () {
    $ana = limpiezaCuenta();
    $beto = limpiezaCuenta();
    limpiezaAviso($beto, 'contrato_creado:1');
    app(AbrirAlerta::class)->ejecutar($beto, 12);
    $antes = Notificacion::query()->deUsuario($beto)->firstOrFail()->updated_at;

    app(MarcarTodasLeidas::class)->ejecutar($ana, [10, 11, 12]);

    $aviso = Notificacion::query()->deUsuario($beto)->firstOrFail();

    expect($aviso->leida_en)->toBeNull()
        ->and($aviso->updated_at->equalTo($antes))->toBeTrue()
        ->and(limpiezaEstadoAlertas($beto))->toBe(['leidas' => [12], 'limpiadas' => []]);
});

// ── 4. Limpiar ────────────────────────────────────────────────────────

test('limpiar da de baja todos los avisos de la cuenta, leídos o no, y limpia las alertas que se le pasan', function () {
    $ana = limpiezaCuenta();
    limpiezaAviso($ana, 'contrato_creado:1');
    limpiezaAviso($ana, 'contrato_creado:2', leido: true);
    limpiezaAviso($ana, 'contrato_creado:3');
    app(AbrirAlerta::class)->ejecutar($ana, 12);

    $limpiadas = app(LimpiarNotificaciones::class)->ejecutar($ana, [10, 12]);

    expect($limpiadas)->toBe(5)
        ->and(Notificacion::query()->deUsuario($ana)->count())->toBe(0)
        ->and(Notificacion::withTrashed()->deUsuario($ana)->count())->toBe(3)
        ->and(app(LecturaNotificaciones::class)->recientesDe($ana, 10))->toBe([])
        // Limpiar es también haber visto: la que no estaba leída queda leída, y la que sí, sigue igual.
        ->and(limpiezaEstadoAlertas($ana))->toBe(['leidas' => [10, 12], 'limpiadas' => [10, 12]]);
});

test('limpiar es idempotente: repetirlo no cambia nada', function () {
    $ana = limpiezaCuenta();
    limpiezaAviso($ana, 'contrato_creado:1');

    app(LimpiarNotificaciones::class)->ejecutar($ana, [10]);
    $bitacoras = Bitacora::query()->count();

    expect(app(LimpiarNotificaciones::class)->ejecutar($ana, [10]))->toBe(0)
        ->and(Bitacora::query()->count())->toBe($bitacoras)
        ->and(AlertaVista::query()->deUsuario($ana)->count())->toBe(1);
});

test('limpiar no toca los avisos ni el estado de las alertas de otra cuenta', function () {
    $ana = limpiezaCuenta();
    $beto = limpiezaCuenta();
    limpiezaAviso($ana, 'contrato_creado:1');
    limpiezaAviso($beto, 'contrato_creado:1');
    limpiezaAviso($beto, 'contrato_creado:2', leido: true);
    app(AbrirAlerta::class)->ejecutar($beto, 10);

    app(LimpiarNotificaciones::class)->ejecutar($ana, [10, 11]);

    expect(Notificacion::query()->deUsuario($beto)->count())->toBe(2)
        ->and(Notificacion::query()->deUsuario($beto)->sinLeer()->count())->toBe(1)
        ->and(app(LecturaNotificaciones::class)->recientesDe($beto, 10))->toHaveCount(2)
        ->and(limpiezaEstadoAlertas($beto))->toBe(['leidas' => [10], 'limpiadas' => []]);
});

test('cada aviso que se limpia deja su fila de bitácora, no un borrado masivo que se la salte', function () {
    $ana = limpiezaCuenta();
    limpiezaAviso($ana, 'contrato_creado:1');
    limpiezaAviso($ana, 'contrato_creado:2');

    app(LimpiarNotificaciones::class)->ejecutar($ana, [10]);

    expect(Bitacora::query()->where('tabla', 'ntf_notificaciones')->where('accion', 'eliminado')->count())->toBe(2)
        ->and(Bitacora::query()->where('tabla', 'ntf_alertas_vistas')->where('accion', 'creado')->count())->toBe(1);
});

test('limpiar no borra físicamente: el aviso sigue en la tabla, dado de baja', function () {
    $ana = limpiezaCuenta();
    $aviso = limpiezaAviso($ana, 'contrato_creado:1');

    app(LimpiarNotificaciones::class)->ejecutar($ana);

    expect(DB::table('ntf_notificaciones')->where('id', $aviso->id)->whereNotNull('deleted_at')->count())->toBe(1);
});
