<?php

use App\Dominios\Distribucion\Dominio\EstadoVersionApk;
use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/*
 * HU-20 — CA "GET /api/version": sin autenticación (la app todavía no tiene
 * token), devuelve la versión vigente autorizada con su URL de descarga
 * firmada.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('r2');
    // `buildTemporaryUrlsUsing` es el escape hatch de Laravel para simular
    // la firma sin depender de credenciales reales de R2 (ni siquiera en
    // testing): en producción, el disco 's3' real firma con el SDK.
    Storage::disk('r2')->buildTemporaryUrlsUsing(
        fn (string $ruta, $expiracion) => "https://r2.test/{$ruta}?firma=simulada",
    );
});

it('responde minima y vigente en null cuando el dueño todavía no autorizó ninguna versión', function () {
    $this->getJson('/api/version')
        ->assertOk()
        ->assertExactJson(['minima' => null, 'vigente' => null]);
});

it('devuelve la versión autorizada como mínima y vigente, con url de descarga firmada', function () {
    VersionApk::factory()->create([
        'version' => '1.4.2',
        'version_code' => 14002,
        'ruta_apk' => 'distribucion/apk/1.4.2.apk',
        'estado' => EstadoVersionApk::Autorizada,
    ]);

    $respuesta = $this->getJson('/api/version')->assertOk();

    $esperado = [
        'version' => '1.4.2',
        'version_code' => 14002,
        'url_descarga' => 'https://r2.test/distribucion/apk/1.4.2.apk?firma=simulada',
    ];

    $respuesta->assertExactJson(['minima' => $esperado, 'vigente' => $esperado]);
});

it('ignora versiones pendientes o rechazadas: solo la autorizada cuenta como vigente', function () {
    VersionApk::factory()->create(['estado' => EstadoVersionApk::Pendiente]);
    VersionApk::factory()->create(['estado' => EstadoVersionApk::Rechazada]);

    $this->getJson('/api/version')
        ->assertOk()
        ->assertExactJson(['minima' => null, 'vigente' => null]);
});

it('no exige autenticación', function () {
    $this->getJson('/api/version')->assertOk();
});
