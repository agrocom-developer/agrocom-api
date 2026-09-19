<?php

use App\Dominios\Campania\Contratos\DatosCampania;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaNoAbierta as ComercialCampaniaNoAbierta;
use App\Dominios\Finanzas\Dominio\Excepciones\CampaniaNoAbierta as FinanzasCampaniaNoAbierta;

/*
 * Guarda de imputación (ADR 0015 punto 1, adenda del 19/9/2026): solo una
 * campaña `abierta` admite contratos, gastos y combustible. La decide
 * `Campania` (`DatosCampania::admiteImputaciones()`); `Comercial` y
 * `Finanzas` reaccionan con su `CampaniaNoAbierta`. Puro, sin app ni DB: sin
 * el framework levantado cada texto sale como su clave, así que se prueba QUÉ
 * mensaje se elige (los textos reales los vigila ClavesDeIdiomaTest).
 */

test('campania: solo la abierta admite imputaciones nuevas', function () {
    $planificada = new DatosCampania(id: 1, codigo: 'X', cerrada: false, abierta: false);
    $abierta = new DatosCampania(id: 1, codigo: 'X', cerrada: false, abierta: true);
    $cerrada = new DatosCampania(id: 1, codigo: 'X', cerrada: true, abierta: false);

    expect($planificada->admiteImputaciones())->toBeFalse()
        ->and($abierta->admiteImputaciones())->toBeTrue()
        ->and($cerrada->admiteImputaciones())->toBeFalse();
});

test('campania no abierta: a una planificada se le pide abrirla, una cerrada dice que no admite más', function (string $modulo, string $clase) {
    expect($clase::paraCampania('2026-2027', cerrada: false)->getMessage())->toBe("{$modulo}.errores.campania_no_abierta")
        ->and($clase::paraCampania('2026-2027', cerrada: true)->getMessage())->toBe("{$modulo}.errores.campania_cerrada");
})->with([
    'comercial' => ['comercial', ComercialCampaniaNoAbierta::class],
    'finanzas' => ['finanzas', FinanzasCampaniaNoAbierta::class],
]);
