<?php

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Dominio\MaquinaEstados\TransicionesCampania;

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
