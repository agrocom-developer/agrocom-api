<?php

use App\Dominios\Notificaciones\Aplicacion\DispositivosPush;
use App\Dominios\Notificaciones\Contratos\EnviadorDePush;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * El token de push de cada aparato (`negociacion-contrato.md` §7): se registra al iniciar
 * sesión y se retira al cerrarla, siempre dentro del alcance de la cuenta del token. Contra
 * la tabla real `ntf_dispositivos` (SQLite en memoria).
 */

uses(TestCase::class);

function pushMundo(): EnviadorDePush
{
    Esquema::migrar(['create_plt_bitacoras_table', 'create_ntf_dispositivos_table']);
    DB::table('ntf_dispositivos')->delete();

    $enviador = new class implements EnviadorDePush
    {
        /** @var list<array{tokens: list<string>, tipo: string, datos: array<string, mixed>}> */
        public array $enviados = [];

        public bool $falla = false;

        public function enviar(array $tokens, string $tipo, array $datos): void
        {
            if ($this->falla) {
                throw new RuntimeException('FCM no responde');
            }

            $this->enviados[] = ['tokens' => $tokens, 'tipo' => $tipo, 'datos' => $datos];
        }
    };
    app()->instance(EnviadorDePush::class, $enviador);

    return $enviador;
}

test('registrar el mismo token dos veces deja una sola fila activa', function () {
    pushMundo();
    $push = app(DispositivosPush::class);

    [$primero, $nuevo] = $push->registrar(10, 'fcm:abc', 'android');
    [$segundo, $nuevoOtraVez] = $push->registrar(10, 'fcm:abc', 'android');

    expect($nuevo)->toBeTrue()->and($nuevoOtraVez)->toBeFalse()->and($segundo->id)->toBe($primero->id)
        ->and(DB::table('ntf_dispositivos')->count())->toBe(1);
});

test('si el aparato cambia de cuenta sin retirar el token, el registro pasa a la cuenta nueva', function () {
    pushMundo();
    $push = app(DispositivosPush::class);

    $push->registrar(10, 'fcm:compartido', 'ios');
    $push->registrar(11, 'fcm:compartido', 'ios');

    expect(DB::table('ntf_dispositivos')->where('token', 'fcm:compartido')->value('usuario_id'))->toBe(11)
        ->and(DB::table('ntf_dispositivos')->count())->toBe(1);
});

test('solo se retira el token propio: el de otra cuenta responde como si no existiera', function () {
    pushMundo();
    $push = app(DispositivosPush::class);
    $push->registrar(10, 'fcm:de-diez', 'android');

    expect($push->retirar(11, 'fcm:de-diez'))->toBeFalse()
        ->and(DB::table('ntf_dispositivos')->whereNull('deleted_at')->count())->toBe(1)
        ->and($push->retirar(10, 'fcm:de-diez'))->toBeTrue()
        ->and($push->retirar(10, 'fcm:de-diez'))->toBeFalse()
        ->and(DB::table('ntf_dispositivos')->whereNull('deleted_at')->count())->toBe(0);

    // Un token retirado puede volver a registrarse (por ejemplo, tras iniciar sesión otra vez).
    [, $nuevo] = $push->registrar(10, 'fcm:de-diez', 'android');
    expect($nuevo)->toBeTrue();
});

test('el aviso llega solo a los tokens activos de la cuenta y un fallo del proveedor no rompe la entrega', function () {
    $enviador = pushMundo();
    $push = app(DispositivosPush::class);
    $push->registrar(10, 'fcm:uno', 'android');
    $push->registrar(10, 'fcm:dos', 'ios');
    $push->registrar(11, 'fcm:ajeno', 'android');
    $push->retirar(10, 'fcm:dos');

    $push->avisar(10, 'oferta_recibida', ['tipo' => 'oferta_recibida', 'recurso_tipo' => 'solicitud', 'recurso_id' => 5]);
    $push->avisar(12, 'oferta_recibida', []);

    expect($enviador->enviados)->toHaveCount(1)
        ->and($enviador->enviados[0]['tokens'])->toBe(['fcm:uno'])
        ->and($enviador->enviados[0]['datos'])->toBe(['tipo' => 'oferta_recibida', 'recurso_tipo' => 'solicitud', 'recurso_id' => 5]);

    $enviador->falla = true;
    $push->avisar(10, 'oferta_recibida', []);

    expect($enviador->enviados)->toHaveCount(1);
});

test('el token no se serializa en las respuestas del modelo', function () {
    pushMundo();
    [$dispositivo] = app(DispositivosPush::class)->registrar(10, 'fcm:secreto', 'android');

    expect(json_encode($dispositivo->toArray()))->not->toContain('fcm:secreto');
});
