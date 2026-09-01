<?php

use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesSesion;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesTrabajo;

/*
 * Tabla de transiciones de `trabajo`/`sesion` (invariante 7 de CLAUDE.md,
 * tarea 09). Pura, sin Eloquent ni DB — igual que el resto de `tests/Unit`
 * (`Pest.php`: "los de Unit son PHPUnit puro"). La creación con el estado
 * inicial (`abrir()`, en Aplicacion/MaquinaEstados) se prueba aparte en
 * `tests/Feature/MaquinaEstadosOperacionesTest.php`, porque persiste en la
 * base.
 */

test('trabajo: abierto puede pasar a cerrado', function () {
    expect(TransicionesTrabajo::permitida(EstadoTrabajo::Abierto, EstadoTrabajo::Cerrado))->toBeTrue();
});

test('trabajo: cerrado no vuelve a abierto', function () {
    expect(TransicionesTrabajo::permitida(EstadoTrabajo::Cerrado, EstadoTrabajo::Abierto))->toBeFalse();
});

test('trabajo: ningún estado se transiciona a sí mismo', function () {
    expect(TransicionesTrabajo::permitida(EstadoTrabajo::Abierto, EstadoTrabajo::Abierto))->toBeFalse()
        ->and(TransicionesTrabajo::permitida(EstadoTrabajo::Cerrado, EstadoTrabajo::Cerrado))->toBeFalse();
});

test('sesion: abierto puede pasar a cerrado', function () {
    expect(TransicionesSesion::permitida(EstadoSesion::Abierto, EstadoSesion::Cerrado))->toBeTrue();
});

test('sesion: cerrado no vuelve a abierto', function () {
    expect(TransicionesSesion::permitida(EstadoSesion::Cerrado, EstadoSesion::Abierto))->toBeFalse();
});

test('sesion: ningún estado se transiciona a sí mismo', function () {
    expect(TransicionesSesion::permitida(EstadoSesion::Abierto, EstadoSesion::Abierto))->toBeFalse()
        ->and(TransicionesSesion::permitida(EstadoSesion::Cerrado, EstadoSesion::Cerrado))->toBeFalse();
});
