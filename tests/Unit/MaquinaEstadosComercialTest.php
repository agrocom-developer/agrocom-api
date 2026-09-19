<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\MaquinaEstados\TransicionesContrato;

/*
 * Tabla de transiciones de `contrato` (invariante 7 de CLAUDE.md, HU-23,
 * tarea 34). Pura, sin Eloquent ni DB — mismo patrón que
 * tests/Unit/MaquinaEstadosOperacionesTest.php. Las guardas de negocio de
 * `activar()` (ventanas cargadas, fecha de inicio no pasada), que sí
 * necesitan un modelo persistido, se prueban en
 * tests/Feature/Comercial/GestionContratosPanelTest.php.
 */

test('contrato: borrador puede pasar a vigente', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Borrador, EstadoContrato::Vigente))->toBeTrue();
});

test('contrato: borrador puede pasar a cancelado', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Borrador, EstadoContrato::Cancelado))->toBeTrue();
});

test('contrato: vigente puede pasar a finalizado', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Vigente, EstadoContrato::Finalizado))->toBeTrue();
});

test('contrato: vigente puede pasar a cancelado', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Vigente, EstadoContrato::Cancelado))->toBeTrue();
});

test('contrato: vigente puede pasar a pausado', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Vigente, EstadoContrato::Pausado))->toBeTrue();
});

test('contrato: pausado vuelve a vigente', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Pausado, EstadoContrato::Vigente))->toBeTrue();
});

test('contrato: pausado no pasa a cancelado', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Pausado, EstadoContrato::Cancelado))->toBeFalse();
});

test('contrato: pausado no pasa a finalizado', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Pausado, EstadoContrato::Finalizado))->toBeFalse();
});

test('contrato: borrador no puede pasar directo a pausado', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Borrador, EstadoContrato::Pausado))->toBeFalse();
});

test('contrato: finalizado no vuelve a vigente', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Finalizado, EstadoContrato::Vigente))->toBeFalse();
});

test('contrato: finalizado no tiene ninguna salida', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Finalizado, EstadoContrato::Cancelado))->toBeFalse();
});

test('contrato: cancelado no tiene ninguna salida', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Cancelado, EstadoContrato::Vigente))->toBeFalse()
        ->and(TransicionesContrato::permitida(EstadoContrato::Cancelado, EstadoContrato::Finalizado))->toBeFalse();
});

test('contrato: borrador no puede pasar directo a finalizado', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Borrador, EstadoContrato::Finalizado))->toBeFalse();
});

test('contrato: ningún estado se transiciona a sí mismo', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Borrador, EstadoContrato::Borrador))->toBeFalse()
        ->and(TransicionesContrato::permitida(EstadoContrato::Vigente, EstadoContrato::Vigente))->toBeFalse()
        ->and(TransicionesContrato::permitida(EstadoContrato::Finalizado, EstadoContrato::Finalizado))->toBeFalse()
        ->and(TransicionesContrato::permitida(EstadoContrato::Cancelado, EstadoContrato::Cancelado))->toBeFalse()
        ->and(TransicionesContrato::permitida(EstadoContrato::Pausado, EstadoContrato::Pausado))->toBeFalse()
        ->and(TransicionesContrato::permitida(EstadoContrato::Conflicto, EstadoContrato::Conflicto))->toBeFalse();
});

/*
 * `conflicto` (ADR 0021): borrador que comparte lote con un contrato que ya
 * retiene lotes. Lo mueve solo el sistema al reconciliar la campaña; un
 * contrato en conflicto nunca se aprueba directo — vuelve a `borrador` y
 * desde ahí se aprueba.
 */

test('contrato: borrador pasa a conflicto y conflicto vuelve a borrador', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Borrador, EstadoContrato::Conflicto))->toBeTrue()
        ->and(TransicionesContrato::permitida(EstadoContrato::Conflicto, EstadoContrato::Borrador))->toBeTrue();
});

test('contrato: conflicto puede cancelarse', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Conflicto, EstadoContrato::Cancelado))->toBeTrue();
});

test('contrato: conflicto no se aprueba directo ni pasa a otro estado operativo', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Conflicto, EstadoContrato::Vigente))->toBeFalse()
        ->and(TransicionesContrato::permitida(EstadoContrato::Conflicto, EstadoContrato::Finalizado))->toBeFalse()
        ->and(TransicionesContrato::permitida(EstadoContrato::Conflicto, EstadoContrato::Pausado))->toBeFalse();
});

test('contrato: solo un borrador puede caer en conflicto', function () {
    expect(TransicionesContrato::permitida(EstadoContrato::Vigente, EstadoContrato::Conflicto))->toBeFalse()
        ->and(TransicionesContrato::permitida(EstadoContrato::Pausado, EstadoContrato::Conflicto))->toBeFalse()
        ->and(TransicionesContrato::permitida(EstadoContrato::Finalizado, EstadoContrato::Conflicto))->toBeFalse()
        ->and(TransicionesContrato::permitida(EstadoContrato::Cancelado, EstadoContrato::Conflicto))->toBeFalse();
});

test('contrato: solo vigente y pausado retienen lotes', function () {
    expect(EstadoContrato::Vigente->retieneLotes())->toBeTrue()
        ->and(EstadoContrato::Pausado->retieneLotes())->toBeTrue()
        ->and(EstadoContrato::Borrador->retieneLotes())->toBeFalse()
        ->and(EstadoContrato::Conflicto->retieneLotes())->toBeFalse()
        ->and(EstadoContrato::Finalizado->retieneLotes())->toBeFalse()
        ->and(EstadoContrato::Cancelado->retieneLotes())->toBeFalse();
});

test('contrato: los valores que retienen lotes salen del propio enum', function () {
    expect(EstadoContrato::valoresQueRetienenLotes())->toBe(['vigente', 'pausado']);
});
