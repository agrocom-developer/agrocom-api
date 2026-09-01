<?php

use App\Dominios\Compartido\Dominio\Excepciones\BorradoFisicoNoPermitido;
use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * ADR 0007 / invariante 8 — mismo criterio que BorradoLogicoTest.php y
 * BorradoLogicoSeguridadPersonalTest.php, para el modelo nuevo de HU-20.
 */

uses(RefreshDatabase::class);

it('delete() hace borrado lógico y saca la versión de los listados por defecto', function () {
    $version = VersionApk::factory()->create();

    $version->delete();

    expect($version->deleted_at)->not->toBeNull()
        ->and(VersionApk::query()->whereKey($version->getKey())->exists())->toBeFalse()
        ->and(VersionApk::withTrashed()->whereKey($version->getKey())->exists())->toBeTrue();
});

it('bloquea el borrado físico: forceDelete() lanza excepción y el registro sobrevive', function () {
    $version = VersionApk::factory()->create();

    expect(fn () => $version->forceDelete())->toThrow(BorradoFisicoNoPermitido::class)
        ->and(VersionApk::withTrashed()->whereKey($version->getKey())->exists())->toBeTrue();
});
