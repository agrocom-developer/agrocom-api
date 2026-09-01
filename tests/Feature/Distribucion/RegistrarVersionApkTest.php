<?php

use App\Dominios\Distribucion\Aplicacion\RegistrarVersionApk;
use App\Dominios\Distribucion\Dominio\EstadoVersionApk;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-20 — registro de una versión del APK: el binario vive en el release de
 * `agrocom-field`, acá solo se guarda su URL y la versión nace `pendiente`
 * — autorizarla es un paso aparte, a cargo de MaquinaEstadosVersionApk.
 */

uses(RefreshDatabase::class);

it('registra la versión con la URL del release, pendiente de autorización', function () {
    $url = 'https://github.com/agrocom-developer/agrocom-field/releases/download/v2.0.0/agrocom-field.apk';

    $version = (new RegistrarVersionApk)->ejecutar('2.0.0', 20000, $url);

    expect($version->version)->toBe('2.0.0')
        ->and($version->version_code)->toBe(20000)
        ->and($version->url_apk)->toBe($url)
        ->and($version->estado)->toBe(EstadoVersionApk::Pendiente);
});
