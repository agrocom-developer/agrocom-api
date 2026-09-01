<?php

use App\Dominios\Distribucion\Aplicacion\SubirVersionApk;
use App\Dominios\Distribucion\Dominio\EstadoVersionApk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * HU-20 — subida del binario al disco `r2` (ADR 0009, extensión 1/9/2026):
 * nunca el disco `public`, y la versión nace `pendiente` — autorizarla es
 * un paso aparte, a cargo de MaquinaEstadosVersionApk.
 */

uses(RefreshDatabase::class);

it('sube el apk al disco r2, en su ruta propia, y crea la versión pendiente', function () {
    Storage::fake('r2');
    Storage::fake('public');

    $archivo = UploadedFile::fake()->create('agrocom-field.apk', 1024);

    $version = (new SubirVersionApk)->ejecutar('2.0.0', 20000, $archivo);

    Storage::disk('r2')->assertExists('distribucion/apk/2.0.0.apk');
    Storage::disk('public')->assertMissing('distribucion/apk/2.0.0.apk');

    expect($version->version)->toBe('2.0.0')
        ->and($version->version_code)->toBe(20000)
        ->and($version->ruta_apk)->toBe('distribucion/apk/2.0.0.apk')
        ->and($version->estado)->toBe(EstadoVersionApk::Pendiente);
});
