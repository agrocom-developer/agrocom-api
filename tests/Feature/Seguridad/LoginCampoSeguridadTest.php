<?php

use App\Dominios\Seguridad\Aplicacion\EmitirTokenDispositivo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

/*
 * Endurecimiento del login de la app de campo: límite por usuario + IP (no
 * solo IP, porque los dispositivos del lote comparten la IP del enlace
 * satelital) y vigencia configurable del token.
 *
 * Los intentos se mandan con un payload incompleto a propósito: el throttle
 * corre antes que la validación, así que responde 429 al pasar el límite y
 * 422 antes, sin tocar la base de datos.
 */

beforeEach(function () {
    RateLimiter::clear('login-campo');
    Cache::flush();
});

function intentarLogin(string $username, string $ip = '203.0.113.7'): TestResponse
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/api/auth/token', ['username' => $username, 'password' => 'incorrecta']);
}

it('bloquea el séptimo intento del mismo usuario desde la misma IP', function () {
    foreach (range(1, 6) as $_) {
        intentarLogin('camila.rojas')->assertStatus(422);
    }

    intentarLogin('camila.rojas')->assertStatus(429);
});

it('no bloquea a otro usuario que comparte la IP del lote', function () {
    foreach (range(1, 7) as $_) {
        intentarLogin('camila.rojas');
    }

    intentarLogin('luis.quispe')->assertStatus(422);
});

it('trata igual el usuario con distinta capitalización o espacios', function () {
    foreach (range(1, 6) as $_) {
        intentarLogin('camila.rojas');
    }

    intentarLogin('  Camila.Rojas ')->assertStatus(429);
});

it('limita por IP el barrido de usuarios distintos', function () {
    foreach (range(1, 60) as $i) {
        intentarLogin("usuario{$i}")->assertStatus(422);
    }

    intentarLogin('usuario61')->assertStatus(429);
});

it('no bloquea al mismo usuario desde otra IP', function () {
    foreach (range(1, 7) as $_) {
        intentarLogin('camila.rojas');
    }

    intentarLogin('camila.rojas', '198.51.100.9')->assertStatus(422);
});

it('define 43200 minutos de vigencia por defecto en la configuración de Sanctum', function () {
    expect(config('sanctum.expiration'))->toBe(43200);
});

it('lee la vigencia de SANCTUM_EXPIRATION', function () {
    expect(file_get_contents(config_path('sanctum.php')))->toContain("env('SANCTUM_EXPIRATION'");
    expect(file_get_contents(base_path('.env.example')))->toContain('SANCTUM_EXPIRATION=');
});

it('fecha el vencimiento del token según sanctum.expiration', function () {
    $this->travelTo(now()->startOfSecond());
    $caso = (new ReflectionClass(EmitirTokenDispositivo::class))->newInstanceWithoutConstructor();
    $vencimiento = (new ReflectionMethod($caso, 'vencimiento'));

    config(['sanctum.expiration' => 43200]);
    expect($vencimiento->invoke($caso)?->equalTo(now()->addMinutes(43200)))->toBeTrue();

    config(['sanctum.expiration' => null]);
    expect($vencimiento->invoke($caso))->toBeNull();
});
