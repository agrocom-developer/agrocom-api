<?php

use App\Dominios\Distribucion\Dominio\EstadoVersionApk;
use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-20 — CA "GET /api/version": sin autenticación (la app todavía no tiene
 * token), devuelve la versión vigente autorizada con la URL de su release en
 * agrocom-field.
 */

uses(RefreshDatabase::class);

it('responde minima y vigente en null cuando el dueño todavía no autorizó ninguna versión', function () {
    $this->getJson('/api/version')
        ->assertOk()
        ->assertExactJson(['minima' => null, 'vigente' => null]);
});

it('devuelve la versión autorizada como mínima y vigente, con la url del release', function () {
    $url = 'https://github.com/agrocom-developer/agrocom-field/releases/download/v1.4.2/agrocom-field.apk';

    VersionApk::factory()->create([
        'version' => '1.4.2',
        'version_code' => 14002,
        'url_apk' => $url,
        'estado' => EstadoVersionApk::Autorizada,
    ]);

    $respuesta = $this->getJson('/api/version')->assertOk();

    $esperado = [
        'version' => '1.4.2',
        'version_code' => 14002,
        'url_descarga' => $url,
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
