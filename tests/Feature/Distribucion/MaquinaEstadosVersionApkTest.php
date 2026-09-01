<?php

use App\Dominios\Distribucion\Aplicacion\MaquinaEstados\MaquinaEstadosVersionApk;
use App\Dominios\Distribucion\Dominio\EstadoVersionApk;
use App\Dominios\Distribucion\Dominio\Excepciones\TransicionVersionApkNoPermitida;
use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * HU-20 — "una sola versión autorizada a la vez" es una invariante de
 * negocio real (análoga a "un rol activo por sesión"): autorizar una versión
 * nueva desautoriza la anterior en la MISMA transacción, nunca un
 * `estado = ...` suelto (invariante 7 de CLAUDE.md, gate en
 * tests/Unit/TransicionesEstadoTest.php).
 */

uses(RefreshDatabase::class);

it('autoriza una versión pendiente', function () {
    $version = VersionApk::factory()->create();

    (new MaquinaEstadosVersionApk)->autorizar($version);

    expect($version->fresh()?->estado)->toBe(EstadoVersionApk::Autorizada);
});

it('autorizar una versión nueva desautoriza la anterior: solo una autorizada a la vez', function () {
    $maquina = new MaquinaEstadosVersionApk;

    $primera = VersionApk::factory()->create(['version' => '1.0.0', 'version_code' => 100]);
    $segunda = VersionApk::factory()->create(['version' => '1.1.0', 'version_code' => 110]);

    $maquina->autorizar($primera);
    $maquina->autorizar($segunda);

    expect($primera->fresh()?->estado)->toBe(EstadoVersionApk::Pendiente)
        ->and($segunda->fresh()?->estado)->toBe(EstadoVersionApk::Autorizada)
        ->and(VersionApk::query()->where('estado', EstadoVersionApk::Autorizada->value)->count())->toBe(1);
});

it('no permite autorizar una versión ya rechazada: la transición no está en la tabla', function () {
    $version = VersionApk::factory()->create(['estado' => EstadoVersionApk::Rechazada]);

    expect(fn () => (new MaquinaEstadosVersionApk)->autorizar($version))
        ->toThrow(TransicionVersionApkNoPermitida::class);
});

it('la base rechaza una segunda fila autorizada si algo la escribiera por fuera del servicio', function () {
    VersionApk::factory()->create(['estado' => EstadoVersionApk::Autorizada]);
    $segunda = VersionApk::factory()->create(['estado' => EstadoVersionApk::Pendiente]);

    expect(fn () => DB::table('dis_versiones_apk')->where('id', $segunda->id)->update(['estado' => 'autorizada']))
        ->toThrow(QueryException::class);
});
