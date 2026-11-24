<?php

use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\TransicionesSolicitud;

/*
 * Ciclo de la solicitud de servicio (especificación del marketplace §5.1, ADR
 * 0027, invariante 7 de CLAUDE.md). Pura, sin Eloquent ni base: la tabla de
 * transiciones y las propiedades de cada estado.
 */

test('el camino feliz recorre el ciclo de punta a punta', function () {
    $camino = [
        EstadoSolicitud::Borrador, EstadoSolicitud::Publicada, EstadoSolicitud::EnAsignacion,
        EstadoSolicitud::Asignada, EstadoSolicitud::EnCamino, EstadoSolicitud::EnEjecucion,
        EstadoSolicitud::Ejecutada, EstadoSolicitud::Completada,
    ];

    foreach (array_slice($camino, 0, -1) as $indice => $desde) {
        expect(TransicionesSolicitud::permitida($desde, $camino[$indice + 1]))->toBeTrue();
    }
});

test('completada y cancelada son terminales', function () {
    foreach ([EstadoSolicitud::Completada, EstadoSolicitud::Cancelada] as $terminal) {
        expect($terminal->esTerminal())->toBeTrue()
            ->and(TransicionesSolicitud::destinosDesde($terminal))->toBe([]);
    }

    $noTerminales = array_filter(EstadoSolicitud::cases(), fn (EstadoSolicitud $e) => ! $e->esTerminal());
    expect($noTerminales)->toHaveCount(10);
});

test('un borrador solo se publica o se cancela', function () {
    expect(TransicionesSolicitud::destinosDesde(EstadoSolicitud::Borrador))
        ->toBe([EstadoSolicitud::Publicada, EstadoSolicitud::Cancelada]);
});

test('sin proveedor el cliente puede republicar o cancelar', function () {
    expect(TransicionesSolicitud::permitida(EstadoSolicitud::SinProveedor, EstadoSolicitud::Publicada))->toBeTrue()
        ->and(TransicionesSolicitud::permitida(EstadoSolicitud::SinProveedor, EstadoSolicitud::Cancelada))->toBeTrue()
        ->and(TransicionesSolicitud::permitida(EstadoSolicitud::SinProveedor, EstadoSolicitud::Asignada))->toBeFalse();
});

test('si el proveedor cancela, la solicitud vuelve a asignación', function () {
    expect(TransicionesSolicitud::permitida(EstadoSolicitud::Asignada, EstadoSolicitud::EnAsignacion))->toBeTrue()
        ->and(TransicionesSolicitud::permitida(EstadoSolicitud::EnCamino, EstadoSolicitud::EnAsignacion))->toBeTrue()
        ->and(TransicionesSolicitud::permitida(EstadoSolicitud::EnEjecucion, EstadoSolicitud::EnAsignacion))->toBeFalse();
});

test('en ejecución solo se termina o se suspende por clima, y no se cancela', function () {
    expect(TransicionesSolicitud::destinosDesde(EstadoSolicitud::EnEjecucion))
        ->toBe([EstadoSolicitud::Ejecutada, EstadoSolicitud::SuspendidaPorClima])
        ->and(EstadoSolicitud::EnEjecucion->admiteCancelacion())->toBeFalse();
});

test('suspendida por clima se reprograma como asignada o se cancela', function () {
    expect(TransicionesSolicitud::destinosDesde(EstadoSolicitud::SuspendidaPorClima))
        ->toBe([EstadoSolicitud::Asignada, EstadoSolicitud::Cancelada]);
});

test('ejecutada pasa a completada o a disputa; una disputa se cierra completada o cancelada', function () {
    expect(TransicionesSolicitud::destinosDesde(EstadoSolicitud::Ejecutada))
        ->toBe([EstadoSolicitud::Completada, EstadoSolicitud::EnDisputa])
        ->and(TransicionesSolicitud::destinosDesde(EstadoSolicitud::EnDisputa))
        ->toBe([EstadoSolicitud::Completada, EstadoSolicitud::Cancelada]);
});

test('ningún estado vuelve a borrador y nadie se transiciona a sí mismo', function () {
    foreach (EstadoSolicitud::cases() as $estado) {
        expect(TransicionesSolicitud::permitida($estado, EstadoSolicitud::Borrador))->toBeFalse()
            ->and(TransicionesSolicitud::permitida($estado, $estado))->toBeFalse();
    }
});

test('solo los estados con equipo fijado llegan a la app de campo', function () {
    expect(EstadoSolicitud::conEquipo())->toBe([
        EstadoSolicitud::Asignada, EstadoSolicitud::EnCamino, EstadoSolicitud::EnEjecucion, EstadoSolicitud::SuspendidaPorClima,
    ])->and(EstadoSolicitud::Publicada->tieneEquipo())->toBeFalse()
        ->and(EstadoSolicitud::EnEjecucion->tieneEquipo())->toBeTrue();
});

test('el cliente cancela mientras no haya un servicio en el lote', function () {
    $cancelables = array_filter(EstadoSolicitud::cases(), fn (EstadoSolicitud $e) => $e->admiteCancelacion());

    expect(array_map(fn (EstadoSolicitud $e) => $e->value, array_values($cancelables)))->toBe([
        'borrador', 'publicada', 'en_asignacion', 'sin_proveedor', 'asignada', 'en_camino', 'suspendida_por_clima', 'en_disputa',
    ]);
});
