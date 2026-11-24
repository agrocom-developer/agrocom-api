<?php

use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\TransicionesSolicitud;

/*
 * Máquina de estados de la solicitud de servicio (`negociacion-contrato.md` §1, invariante 7
 * de CLAUDE.md). Pura, sin Eloquent ni base: la tabla COMPLETA de transiciones permitidas y
 * prohibidas, y las propiedades de cada estado.
 */

/** @return array<string, list<string>> */
function transicionesEsperadas(): array
{
    return [
        'borrador' => ['abierta', 'cancelada'],
        'abierta' => ['con_ofertas', 'adjudicada', 'expirada', 'cancelada'],
        'con_ofertas' => ['abierta', 'adjudicada', 'expirada', 'cancelada'],
        'adjudicada' => ['en_ejecucion', 'abierta', 'cancelada'],
        'en_ejecucion' => ['finalizada'],
        'finalizada' => ['calificada', 'disputada'],
        'calificada' => ['disputada'],
        'disputada' => ['finalizada', 'calificada', 'cancelada'],
        'expirada' => ['abierta'],
        'cancelada' => [],
    ];
}

test('la tabla de transiciones permite exactamente lo que dice la especificación y nada más', function () {
    $esperadas = transicionesEsperadas();

    foreach (EstadoSolicitud::cases() as $desde) {
        foreach (EstadoSolicitud::cases() as $hacia) {
            expect(TransicionesSolicitud::permitida($desde, $hacia))
                ->toBe(in_array($hacia->value, $esperadas[$desde->value], true), "{$desde->value} -> {$hacia->value}");
        }
    }
});

test('el camino feliz recorre el ciclo de punta a punta', function () {
    $camino = [
        EstadoSolicitud::Borrador, EstadoSolicitud::Abierta, EstadoSolicitud::ConOfertas, EstadoSolicitud::Adjudicada,
        EstadoSolicitud::EnEjecucion, EstadoSolicitud::Finalizada, EstadoSolicitud::Calificada,
    ];

    foreach (array_slice($camino, 0, -1) as $indice => $desde) {
        expect(TransicionesSolicitud::permitida($desde, $camino[$indice + 1]))->toBeTrue();
    }
});

test('calificada, cancelada y expirada son terminales para la lista de activas', function () {
    foreach ([EstadoSolicitud::Calificada, EstadoSolicitud::Cancelada, EstadoSolicitud::Expirada] as $terminal) {
        expect($terminal->esTerminal())->toBeTrue();
    }

    expect(TransicionesSolicitud::destinosDesde(EstadoSolicitud::Cancelada))->toBe([])
        ->and(EstadoSolicitud::EnEjecucion->esTerminal())->toBeFalse();
});

test('no existe cancelación desde en_ejecucion: lo no ejecutado se cierra y, si hay desacuerdo, se disputa', function () {
    expect(TransicionesSolicitud::permitida(EstadoSolicitud::EnEjecucion, EstadoSolicitud::Cancelada))->toBeFalse()
        ->and(TransicionesSolicitud::destinosDesde(EstadoSolicitud::EnEjecucion))->toBe([EstadoSolicitud::Finalizada])
        ->and(TransicionesSolicitud::permitida(EstadoSolicitud::Finalizada, EstadoSolicitud::Disputada))->toBeTrue();
});

test('ningún estado vuelve a borrador y nadie se transiciona a sí mismo', function () {
    foreach (EstadoSolicitud::cases() as $estado) {
        expect(TransicionesSolicitud::permitida($estado, EstadoSolicitud::Borrador))->toBeFalse()
            ->and(TransicionesSolicitud::permitida($estado, $estado))->toBeFalse();
    }
});

test('solo los estados con equipo fijado llegan a la app de campo', function () {
    expect(EstadoSolicitud::conEquipo())->toBe([EstadoSolicitud::Adjudicada, EstadoSolicitud::EnEjecucion])
        ->and(EstadoSolicitud::Abierta->tieneEquipo())->toBeFalse()
        ->and(EstadoSolicitud::EnEjecucion->tieneEquipo())->toBeTrue();
});

test('solo abierta y con_ofertas están en el mercado de los pilotos', function () {
    $enMercado = array_filter(EstadoSolicitud::cases(), fn (EstadoSolicitud $e) => $e->enMercado());

    expect(array_map(fn (EstadoSolicitud $e) => $e->value, array_values($enMercado)))->toBe(['abierta', 'con_ofertas']);
});

test('el cliente cancela mientras la solicitud no esté en ejecución', function () {
    $cancelables = array_filter(EstadoSolicitud::cases(), fn (EstadoSolicitud $e) => $e->admiteCancelacion());

    expect(array_map(fn (EstadoSolicitud $e) => $e->value, array_values($cancelables)))->toBe([
        'borrador', 'abierta', 'con_ofertas', 'adjudicada', 'disputada',
    ]);
});

test('toda transición apunta a un estado que existe y cada estado tiene fila en la tabla', function () {
    $esperadas = transicionesEsperadas();

    expect(array_keys($esperadas))->toEqualCanonicalizing(array_map(fn (EstadoSolicitud $e) => $e->value, EstadoSolicitud::cases()));
});
