<?php

use App\Dominios\Notificaciones\Aplicacion\EliminacionDeNotificaciones;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * Lo que Notificaciones aporta a la eliminación de una cuenta (ADR 0047, punto 6): se borran de verdad (no solo en lo lógico) los
 * tokens de push al pedirla y, al ejecutarla, también los avisos de la cuenta; los de otra cuenta quedan.
 */

uses(TestCase::class);

beforeEach(function () {
    Esquema::migrar(['create_ntf_notificaciones_table', 'create_ntf_dispositivos_table']);

    DB::table('ntf_notificaciones')->delete();
    DB::table('ntf_dispositivos')->delete();

    $ahora = now();
    foreach ([[701, null], [701, $ahora], [702, null]] as $i => [$usuario, $borrado]) {
        DB::table('ntf_dispositivos')->insert(['usuario_id' => $usuario, 'token' => "tok-{$i}", 'plataforma' => 'android', 'created_at' => $ahora, 'updated_at' => $ahora, 'deleted_at' => $borrado]);
        DB::table('ntf_notificaciones')->insert(['usuario_id' => $usuario, 'tipo' => 'oferta_recibida', 'clave_evento' => "evento-{$i}", 'recurso_tipo' => 'solicitud', 'recurso_id' => $i, 'created_at' => $ahora, 'updated_at' => $ahora, 'deleted_at' => $borrado]);
    }
});

it('al pedirla borra de verdad los tokens de push de la cuenta y no toca los de otra', function () {
    app(EliminacionDeNotificaciones::class)->alSolicitar(701);

    expect(DB::table('ntf_dispositivos')->pluck('usuario_id')->all())->toBe([702])
        ->and(DB::table('ntf_notificaciones')->count())->toBe(3);
});

it('al ejecutarla borra de verdad los avisos y los tokens de la cuenta, incluso los ya borrados en lo lógico', function () {
    app(EliminacionDeNotificaciones::class)->anonimizar(701);

    expect(DB::table('ntf_dispositivos')->pluck('usuario_id')->all())->toBe([702])
        ->and(DB::table('ntf_notificaciones')->pluck('usuario_id')->all())->toBe([702]);
});

it('no impide eliminar nada', function () {
    expect(app(EliminacionDeNotificaciones::class)->impedimentos(701))->toBe([]);
});
