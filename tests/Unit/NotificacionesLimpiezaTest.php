<?php

use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Notificaciones\Aplicacion\LimpiarNotificaciones;
use App\Dominios\Notificaciones\Aplicacion\MarcarTodasLeidas;
use App\Dominios\Notificaciones\Contratos\LecturaNotificaciones;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Notificaciones\Infraestructura\Eloquent\Notificacion;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
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
 *   1. «Marcar todas» y «Limpiar» solo tocan a la cuenta que los pide: los avisos de
 *      otra cuenta quedan idénticos (exposición de datos entre usuarios: categoría que
 *      CLAUDE.md pide cubrir).
 *   2. «Limpiar» da de baja (soft delete) todos los avisos de la cuenta, leídos o no,
 *      y cada baja deja su fila de bitácora (invariantes 8 y 9).
 *   3. Lo limpiado no vuelve a la campana.
 */

uses(TestCase::class);

function limpiezaEsquema(): void
{
    Schema::disableForeignKeyConstraints();

    $migraciones = [
        'create_plt_bitacoras_table',
        'create_sec_user_table',
        'create_sec_user_preferencia_table',
        'create_ntf_notificaciones_table',
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
        'tipo' => TipoNotificacion::TrabajoCerrado,
        'clave_evento' => $clave,
        'parametros' => ['cliente' => 'Cliente', 'hectareas' => '10,00'],
        'recurso_tipo' => RecursoNotificable::Trabajo,
        'recurso_id' => 1,
        'leida_en' => $leido ? now() : null,
    ]);
}

beforeEach(function () {
    limpiezaEsquema();
});

// ── 1. Marcar todas: solo la cuenta que lo pide ────────────────────────────────

test('marcar todas marca los avisos de la cuenta', function () {
    $ana = limpiezaCuenta();
    limpiezaAviso($ana, 'trabajo_cerrado:1');
    limpiezaAviso($ana, 'trabajo_cerrado:2');

    $marcadas = app(MarcarTodasLeidas::class)->ejecutar($ana);

    expect($marcadas)->toBe(2)
        ->and(Notificacion::query()->deUsuario($ana)->sinLeer()->count())->toBe(0)
        ->and(app(MarcarTodasLeidas::class)->ejecutar($ana))->toBe(0);
});

test('marcar todas no toca los avisos de otra cuenta', function () {
    $ana = limpiezaCuenta();
    $beto = limpiezaCuenta();
    limpiezaAviso($beto, 'trabajo_cerrado:1');
    $antes = Notificacion::query()->deUsuario($beto)->firstOrFail()->updated_at;

    app(MarcarTodasLeidas::class)->ejecutar($ana);

    $aviso = Notificacion::query()->deUsuario($beto)->firstOrFail();

    expect($aviso->leida_en)->toBeNull()
        ->and($aviso->updated_at->equalTo($antes))->toBeTrue();
});

// ── 2. Limpiar ────────────────────────────────────────────────────────

test('limpiar da de baja todos los avisos de la cuenta, leídos o no', function () {
    $ana = limpiezaCuenta();
    limpiezaAviso($ana, 'trabajo_cerrado:1');
    limpiezaAviso($ana, 'trabajo_cerrado:2', leido: true);
    limpiezaAviso($ana, 'trabajo_cerrado:3');

    $limpiadas = app(LimpiarNotificaciones::class)->ejecutar($ana);

    expect($limpiadas)->toBe(3)
        ->and(Notificacion::query()->deUsuario($ana)->count())->toBe(0)
        ->and(Notificacion::withTrashed()->deUsuario($ana)->count())->toBe(3)
        ->and(app(LecturaNotificaciones::class)->recientesDe($ana, 10))->toBe([]);
});

test('limpiar es idempotente: repetirlo no cambia nada', function () {
    $ana = limpiezaCuenta();
    limpiezaAviso($ana, 'trabajo_cerrado:1');

    app(LimpiarNotificaciones::class)->ejecutar($ana);
    $bitacoras = Bitacora::query()->count();

    expect(app(LimpiarNotificaciones::class)->ejecutar($ana))->toBe(0)
        ->and(Bitacora::query()->count())->toBe($bitacoras);
});

test('limpiar no toca los avisos de otra cuenta', function () {
    $ana = limpiezaCuenta();
    $beto = limpiezaCuenta();
    limpiezaAviso($ana, 'trabajo_cerrado:1');
    limpiezaAviso($beto, 'trabajo_cerrado:1');
    limpiezaAviso($beto, 'trabajo_cerrado:2', leido: true);

    app(LimpiarNotificaciones::class)->ejecutar($ana);

    expect(Notificacion::query()->deUsuario($beto)->count())->toBe(2)
        ->and(Notificacion::query()->deUsuario($beto)->sinLeer()->count())->toBe(1)
        ->and(app(LecturaNotificaciones::class)->recientesDe($beto, 10))->toHaveCount(2);
});

test('cada aviso que se limpia deja su fila de bitácora, no un borrado masivo que se la salte', function () {
    $ana = limpiezaCuenta();
    limpiezaAviso($ana, 'trabajo_cerrado:1');
    limpiezaAviso($ana, 'trabajo_cerrado:2');

    app(LimpiarNotificaciones::class)->ejecutar($ana);

    expect(Bitacora::query()->where('tabla', 'ntf_notificaciones')->where('accion', 'eliminado')->count())->toBe(2);
});

test('limpiar no borra físicamente: el aviso sigue en la tabla, dado de baja', function () {
    $ana = limpiezaCuenta();
    $aviso = limpiezaAviso($ana, 'trabajo_cerrado:1');

    app(LimpiarNotificaciones::class)->ejecutar($ana);

    expect(DB::table('ntf_notificaciones')->where('id', $aviso->id)->whereNotNull('deleted_at')->count())->toBe(1);
});
