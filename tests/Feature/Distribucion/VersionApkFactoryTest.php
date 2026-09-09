<?php

use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * `dis_versiones_apk` tiene índice único parcial sobre `version`, pero la
 * factory sorteaba esa columna con dos `numberBetween(0, 20)` sin `unique()`:
 * 441 combinaciones posibles, así que un test que creara un puñado de
 * versiones fallaba de a ratos. La tarea 71 lo documentó como flake de
 * `MaquinaEstadosVersionApkTest`. Este test fija el contrato de la factory
 * para que la colisión no pueda volver por descuido.
 */

uses(RefreshDatabase::class);

it('crea doscientas cincuenta versiones sin colisionar el índice único de version', function () {
    VersionApk::factory()->count(250)->create();

    expect(VersionApk::query()->count())->toBe(250)
        ->and(VersionApk::query()->distinct()->count('version'))->toBe(250)
        ->and(VersionApk::query()->distinct()->count('version_code'))->toBe(250);
});

it('deja pasar la version explícita del test que la fija', function () {
    VersionApk::factory()
        ->state(new Sequence(['version' => '9.9.9'], ['version' => '9.9.8']))
        ->count(2)
        ->create();

    expect(VersionApk::query()->pluck('version')->all())->toEqualCanonicalizing(['9.9.9', '9.9.8']);
});

it('sigue rechazando dos versiones con el mismo número', function () {
    VersionApk::factory()->create(['version' => '4.0.0']);

    expect(fn () => VersionApk::factory()->create(['version' => '4.0.0']))
        ->toThrow(QueryException::class);
});
