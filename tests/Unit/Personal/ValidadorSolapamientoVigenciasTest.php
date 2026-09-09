<?php

use App\Dominios\Personal\Dominio\ValidadorSolapamientoVigencias;

/*
 * Aviso de solapamiento, no bloqueo (tarea 72, HU-49, ADR 0015 punto 3):
 * pura, sin Eloquent ni DB — mismo patrón que
 * tests/Unit/MaquinaEstadosCampaniaTest.php. Sirve igual para "persona en
 * dos equipos" y para "recurso en dos equipos": el validador no distingue
 * el sujeto, solo compara vigencias contra vigencias.
 */

test('sin vigencias existentes, nunca hay rechazo ni aviso', function () {
    $resultado = ValidadorSolapamientoVigencias::evaluar(
        vigenciasExistentes: [],
        nueva: ['desde' => '2026-03-01', 'hasta' => null],
        equipoTrabajoId: 1,
    );

    expect($resultado->rechazada)->toBeFalse()
        ->and($resultado->equiposEnAviso)->toBe([])
        ->and($resultado->tieneAviso())->toBeFalse();
});

test('vigencias que no se tocan en el tiempo, en otro equipo, no generan aviso', function () {
    $resultado = ValidadorSolapamientoVigencias::evaluar(
        vigenciasExistentes: [
            ['equipo_trabajo_id' => 2, 'desde' => '2026-01-01', 'hasta' => '2026-01-31'],
        ],
        nueva: ['desde' => '2026-03-01', 'hasta' => null],
        equipoTrabajoId: 1,
    );

    expect($resultado->rechazada)->toBeFalse()
        ->and($resultado->equiposEnAviso)->toBe([]);
});

test('misma persona vigente en OTRO equipo con fechas que se pisan: se avisa, no se rechaza', function () {
    $resultado = ValidadorSolapamientoVigencias::evaluar(
        vigenciasExistentes: [
            ['equipo_trabajo_id' => 2, 'desde' => '2026-03-01', 'hasta' => '2026-03-15'],
        ],
        nueva: ['desde' => '2026-03-10', 'hasta' => '2026-03-20'],
        equipoTrabajoId: 1,
    );

    expect($resultado->rechazada)->toBeFalse()
        ->and($resultado->equiposEnAviso)->toBe([2])
        ->and($resultado->tieneAviso())->toBeTrue();
});

test('misma persona en dos equipos distintos superpuestos: el aviso lista los dos, sin duplicar', function () {
    $resultado = ValidadorSolapamientoVigencias::evaluar(
        vigenciasExistentes: [
            ['equipo_trabajo_id' => 2, 'desde' => '2026-03-01', 'hasta' => null],
            ['equipo_trabajo_id' => 3, 'desde' => '2026-03-05', 'hasta' => '2026-03-08'],
            ['equipo_trabajo_id' => 2, 'desde' => '2026-06-01', 'hasta' => '2026-06-30'],
        ],
        nueva: ['desde' => '2026-03-01', 'hasta' => '2026-03-31'],
        equipoTrabajoId: 1,
    );

    expect($resultado->rechazada)->toBeFalse()
        ->and($resultado->equiposEnAviso)->toBe([2, 3]);
});

test('misma persona en el MISMO equipo con fechas que se pisan: se rechaza', function () {
    $resultado = ValidadorSolapamientoVigencias::evaluar(
        vigenciasExistentes: [
            ['equipo_trabajo_id' => 1, 'desde' => '2026-03-01', 'hasta' => '2026-03-15'],
        ],
        nueva: ['desde' => '2026-03-10', 'hasta' => '2026-03-20'],
        equipoTrabajoId: 1,
    );

    expect($resultado->rechazada)->toBeTrue()
        ->and($resultado->equiposEnAviso)->toBe([]);
});

test('el mismo equipo con vigencias que NO se pisan no se rechaza (reemplazo de auxiliar en el tiempo)', function () {
    $resultado = ValidadorSolapamientoVigencias::evaluar(
        vigenciasExistentes: [
            ['equipo_trabajo_id' => 1, 'desde' => '2026-01-01', 'hasta' => '2026-02-28'],
        ],
        nueva: ['desde' => '2026-03-01', 'hasta' => null],
        equipoTrabajoId: 1,
    );

    expect($resultado->rechazada)->toBeFalse()
        ->and($resultado->equiposEnAviso)->toBe([]);
});

test('vigente (hasta null) se solapa con cualquier fecha posterior a su inicio', function () {
    $resultado = ValidadorSolapamientoVigencias::evaluar(
        vigenciasExistentes: [
            ['equipo_trabajo_id' => 1, 'desde' => '2026-01-01', 'hasta' => null],
        ],
        nueva: ['desde' => '2030-01-01', 'hasta' => '2030-06-01'],
        equipoTrabajoId: 1,
    );

    expect($resultado->rechazada)->toBeTrue();
});

test('los extremos que se tocan (uno termina el día en que el otro empieza) cuentan como solapamiento', function () {
    $resultado = ValidadorSolapamientoVigencias::evaluar(
        vigenciasExistentes: [
            ['equipo_trabajo_id' => 1, 'desde' => '2026-03-01', 'hasta' => '2026-03-10'],
        ],
        nueva: ['desde' => '2026-03-10', 'hasta' => '2026-03-20'],
        equipoTrabajoId: 1,
    );

    expect($resultado->rechazada)->toBeTrue();
});

test('el día inmediato siguiente ya no se solapa', function () {
    $resultado = ValidadorSolapamientoVigencias::evaluar(
        vigenciasExistentes: [
            ['equipo_trabajo_id' => 1, 'desde' => '2026-03-01', 'hasta' => '2026-03-10'],
        ],
        nueva: ['desde' => '2026-03-11', 'hasta' => '2026-03-20'],
        equipoTrabajoId: 1,
    );

    expect($resultado->rechazada)->toBeFalse();
});

test('un rechazo por el mismo equipo corta antes de acumular avisos de otros equipos', function () {
    $resultado = ValidadorSolapamientoVigencias::evaluar(
        vigenciasExistentes: [
            ['equipo_trabajo_id' => 2, 'desde' => '2026-03-01', 'hasta' => '2026-03-31'],
            ['equipo_trabajo_id' => 1, 'desde' => '2026-03-05', 'hasta' => '2026-03-10'],
        ],
        nueva: ['desde' => '2026-03-01', 'hasta' => '2026-03-31'],
        equipoTrabajoId: 1,
    );

    expect($resultado->rechazada)->toBeTrue()
        ->and($resultado->equiposEnAviso)->toBe([]);
});
