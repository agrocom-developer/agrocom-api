<?php

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Dominio\MaquinaEstados\TransicionesCampania;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;

/*
 * Tabla de transiciones de `campania` (invariante 7 de CLAUDE.md, ADR 0015
 * punto 1, tarea 69). Pura, sin Eloquent ni DB — mismo patrón que
 * tests/Unit/MaquinaEstadosComercialTest.php. La guarda de solapamiento de
 * `abrir()`, que sí necesita datos persistidos, se prueba en
 * tests/Feature/Campania/GestionCampaniasPanelTest.php.
 */

test('campania: planificada puede pasar a abierta', function () {
    expect(TransicionesCampania::permitida(EstadoCampania::Planificada, EstadoCampania::Abierta))->toBeTrue();
});

test('campania: abierta puede pasar a cerrada', function () {
    expect(TransicionesCampania::permitida(EstadoCampania::Abierta, EstadoCampania::Cerrada))->toBeTrue();
});

test('campania: cerrada no vuelve a abierta', function () {
    expect(TransicionesCampania::permitida(EstadoCampania::Cerrada, EstadoCampania::Abierta))->toBeFalse();
});

test('campania: cerrada no tiene ninguna salida', function () {
    expect(TransicionesCampania::permitida(EstadoCampania::Cerrada, EstadoCampania::Planificada))->toBeFalse()
        ->and(TransicionesCampania::permitida(EstadoCampania::Cerrada, EstadoCampania::Abierta))->toBeFalse();
});

test('campania: planificada no puede pasar directo a cerrada', function () {
    expect(TransicionesCampania::permitida(EstadoCampania::Planificada, EstadoCampania::Cerrada))->toBeFalse();
});

test('campania: ningún estado se transiciona a sí mismo', function () {
    expect(TransicionesCampania::permitida(EstadoCampania::Planificada, EstadoCampania::Planificada))->toBeFalse()
        ->and(TransicionesCampania::permitida(EstadoCampania::Abierta, EstadoCampania::Abierta))->toBeFalse()
        ->and(TransicionesCampania::permitida(EstadoCampania::Cerrada, EstadoCampania::Cerrada))->toBeFalse();
});

/*
 * "Activa"/"Inactiva" (HU-77, corregido el 19/9/2026): etiqueta derivada del
 * estado, nunca una columna. Solo la campaña abierta es actividad en curso.
 */
test('campania: solo la abierta es activa', function () {
    expect((new Campania(['estado' => EstadoCampania::Planificada]))->esActiva())->toBeFalse()
        ->and((new Campania(['estado' => EstadoCampania::Abierta]))->esActiva())->toBeTrue()
        ->and((new Campania(['estado' => EstadoCampania::Cerrada]))->esActiva())->toBeFalse();
});
