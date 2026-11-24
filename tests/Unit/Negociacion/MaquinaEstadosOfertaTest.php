<?php

use App\Dominios\Negociacion\Dominio\EstadoOferta;
use App\Dominios\Negociacion\Dominio\TransicionesOferta;

/*
 * Máquina de estados de la oferta (`negociacion-contrato.md` §2, invariante 7 de CLAUDE.md):
 * la tabla completa de transiciones permitidas y prohibidas. Pura, sin base.
 */

test('una oferta vigente puede aceptarse, reemplazarse, rechazarse, retirarse, vencer o cerrarse', function () {
    foreach ([EstadoOferta::Aceptada, EstadoOferta::Reemplazada, EstadoOferta::Rechazada, EstadoOferta::Retirada, EstadoOferta::Vencida, EstadoOferta::Cerrada] as $destino) {
        expect(TransicionesOferta::permitida(EstadoOferta::Vigente, $destino))->toBeTrue($destino->value);
    }
});

test('solo una oferta aceptada puede cerrarse después, cuando el proveedor devuelve la solicitud', function () {
    expect(TransicionesOferta::permitida(EstadoOferta::Aceptada, EstadoOferta::Cerrada))->toBeTrue();

    foreach (EstadoOferta::cases() as $destino) {
        if ($destino !== EstadoOferta::Cerrada) {
            expect(TransicionesOferta::permitida(EstadoOferta::Aceptada, $destino))->toBeFalse($destino->value);
        }
    }
});

test('los estados finales de una oferta no tienen salida', function () {
    foreach ([EstadoOferta::Reemplazada, EstadoOferta::Rechazada, EstadoOferta::Retirada, EstadoOferta::Vencida, EstadoOferta::Cerrada] as $final) {
        foreach (EstadoOferta::cases() as $destino) {
            expect(TransicionesOferta::permitida($final, $destino))->toBeFalse("{$final->value} -> {$destino->value}");
        }
    }
});

test('nadie vuelve a vigente y nadie se transiciona a sí mismo', function () {
    foreach (EstadoOferta::cases() as $estado) {
        expect(TransicionesOferta::permitida($estado, EstadoOferta::Vigente))->toBeFalse()
            ->and(TransicionesOferta::permitida($estado, $estado))->toBeFalse();
    }
});
