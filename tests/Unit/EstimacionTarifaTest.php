<?php

use App\Dominios\Solicitudes\Dominio\AportaProducto;
use App\Dominios\Solicitudes\Dominio\EstimacionTarifa;
use App\Dominios\Solicitudes\Dominio\TipoAplicacionServicio;

/*
 * Tarifa de una solicitud (especificación §8.2, política de comisión):
 * hectáreas por tarifa por hectárea, más recargos explícitos. Decimal exacto,
 * nunca float (invariante 6 de CLAUDE.md). Pura.
 */

function tarifaDePrueba(): EstimacionTarifa
{
    return new EstimacionTarifa(
        tarifasPorTipo: ['defecto' => '120.00', 'insecticida' => '130.00'],
        recargoUrgentePct: '20',
        recargoAccesoDificilPct: '10',
        recargoProductoHectarea: '60.00',
    );
}

test('sin recargos el monto es hectáreas por tarifa', function () {
    $estimacion = tarifaDePrueba()->estimar(TipoAplicacionServicio::Herbicida, '80', false, false, AportaProducto::Cliente);

    expect($estimacion->subtotalBase)->toBe('9600.00')
        ->and($estimacion->montoEstimado)->toBe('9600.00')
        ->and($estimacion->tarifaHectarea)->toBe('120.0000')
        ->and($estimacion->recargoUrgente)->toBe('0.00');
});

test('la tarifa depende del tipo de aplicación y el defecto cubre al que no figura', function () {
    $insecticida = tarifaDePrueba()->estimar(TipoAplicacionServicio::Insecticida, '10', false, false, AportaProducto::Cliente);
    $otro = tarifaDePrueba()->estimar(TipoAplicacionServicio::Otro, '10', false, false, AportaProducto::Cliente);

    expect($insecticida->montoEstimado)->toBe('1300.00')
        ->and($otro->montoEstimado)->toBe('1200.00');
});

test('cada recargo se muestra por separado y se suma al total', function () {
    $estimacion = tarifaDePrueba()->estimar(TipoAplicacionServicio::Herbicida, '80', true, true, AportaProducto::Proveedor);

    expect($estimacion->recargoUrgente)->toBe('1920.00')
        ->and($estimacion->recargoAccesoDificil)->toBe('960.00')
        ->and($estimacion->recargoProducto)->toBe('4800.00')
        ->and($estimacion->montoEstimado)->toBe('17280.00')
        ->and($estimacion->tarifaHectarea)->toBe('216.0000');
});

test('las hectáreas con decimales no pierden exactitud', function () {
    // 0.1 + 0.2 no es 0.3 en float: en decimal exacto, sí.
    $estimacion = tarifaDePrueba()->estimar(TipoAplicacionServicio::Herbicida, '33.3333', false, false, AportaProducto::Cliente);

    expect($estimacion->montoEstimado)->toBe('4000.00')
        ->and($estimacion->hectareas)->toBe('33.3333');
});

test('el monto se redondea a dos decimales, mitad hacia arriba', function () {
    $estimacion = new EstimacionTarifa(['defecto' => '0.005'], '0', '0', '0');

    expect($estimacion->estimar(TipoAplicacionServicio::Otro, '1', false, false, AportaProducto::Cliente)->montoEstimado)->toBe('0.01');
});
