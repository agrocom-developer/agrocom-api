<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * Registro público del marketplace (ADR 0032, especificación §10): crea cuentas
 * sin autenticación, así que lo protege un límite por IP, y todo lo demás de
 * `/api/proveedores/*` exige token.
 *
 * Los intentos se mandan con un payload vacío a propósito: el throttle corre
 * antes que la validación, así que responde 429 al pasar el límite y 422 antes,
 * sin tocar la base de datos.
 */

beforeEach(function () {
    RateLimiter::clear('registro-publico');
    Cache::flush();
});

function intentarRegistro(string $ruta, string $ip = '203.0.113.9'): TestResponse
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson($ruta, []);
}

dataset('registros públicos', ['/api/proveedores/registro', '/api/clientes/registro']);

it('bloquea el sexto registro por minuto desde la misma IP', function (string $ruta) {
    foreach (range(1, 5) as $_) {
        intentarRegistro($ruta)->assertStatus(422);
    }

    intentarRegistro($ruta)->assertStatus(429);
})->with('registros públicos');

it('comparte el límite entre el registro de clientes y el de proveedores', function () {
    foreach (range(1, 5) as $_) {
        intentarRegistro('/api/clientes/registro');
    }

    intentarRegistro('/api/proveedores/registro')->assertStatus(429);
});

it('no bloquea a otra IP', function (string $ruta) {
    foreach (range(1, 6) as $_) {
        intentarRegistro($ruta);
    }

    intentarRegistro($ruta, '203.0.113.10')->assertStatus(422);
})->with('registros públicos');

it('rechaza sin token cada ruta de gestión del proveedor', function () {
    $rutas = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($ruta) => str_starts_with($ruta->uri(), 'api/proveedores/') || $ruta->uri() === 'api/clientes/mi')
        ->reject(fn ($ruta) => $ruta->uri() === 'api/proveedores/registro');

    expect($rutas)->not->toBeEmpty();

    foreach ($rutas as $ruta) {
        expect(in_array('auth:sanctum', $ruta->gatherMiddleware(), true))->toBeTrue("{$ruta->uri()} no exige token");
    }
});

it('responde 401 sin token a la gestión del proveedor', function () {
    $this->getJson('/api/proveedores/mi')->assertStatus(401);
    $this->getJson('/api/proveedores/pilotos')->assertStatus(401);
    $this->postJson('/api/proveedores/drones', [])->assertStatus(401);
    $this->getJson('/api/notificaciones')->assertStatus(401);
});

it('ya no publica un catálogo de zonas ni su resumen: el mercado no tiene zonas (ADR 0045)', function () {
    $rutas = collect(Route::getRoutes()->getRoutes());

    expect($rutas->first(fn ($r) => $r->uri() === 'api/zonas'))->toBeNull()
        ->and($rutas->first(fn ($r) => $r->uri() === 'api/solicitudes-abiertas/resumen-zonas'))->toBeNull()
        ->and($rutas->first(fn ($r) => str_starts_with((string) $r->getName(), 'panel.zonas.')))->toBeNull();
});
