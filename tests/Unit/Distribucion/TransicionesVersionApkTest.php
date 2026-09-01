<?php

use App\Dominios\Distribucion\Dominio\EstadoVersionApk;
use App\Dominios\Distribucion\Dominio\TransicionesVersionApk;

/*
 * Tabla pura de transiciones (HU-20) — sin Eloquent, sin base de datos.
 */

it('permite pendiente → autorizada y pendiente → rechazada', function () {
    expect(TransicionesVersionApk::permitida(EstadoVersionApk::Pendiente, EstadoVersionApk::Autorizada))->toBeTrue()
        ->and(TransicionesVersionApk::permitida(EstadoVersionApk::Pendiente, EstadoVersionApk::Rechazada))->toBeTrue();
});

it('permite volver a pendiente desde autorizada (desautorizar) o desde rechazada (reconsiderar)', function () {
    expect(TransicionesVersionApk::permitida(EstadoVersionApk::Autorizada, EstadoVersionApk::Pendiente))->toBeTrue()
        ->and(TransicionesVersionApk::permitida(EstadoVersionApk::Rechazada, EstadoVersionApk::Pendiente))->toBeTrue();
});

it('no permite saltar directo entre autorizada y rechazada, en ningún sentido', function () {
    expect(TransicionesVersionApk::permitida(EstadoVersionApk::Autorizada, EstadoVersionApk::Rechazada))->toBeFalse()
        ->and(TransicionesVersionApk::permitida(EstadoVersionApk::Rechazada, EstadoVersionApk::Autorizada))->toBeFalse();
});

it('no permite quedarse en el mismo estado', function (EstadoVersionApk $estado) {
    expect(TransicionesVersionApk::permitida($estado, $estado))->toBeFalse();
})->with([
    'pendiente' => [EstadoVersionApk::Pendiente],
    'autorizada' => [EstadoVersionApk::Autorizada],
    'rechazada' => [EstadoVersionApk::Rechazada],
]);
