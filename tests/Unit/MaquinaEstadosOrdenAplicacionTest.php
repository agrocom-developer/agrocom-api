<?php

use App\Dominios\Operaciones\Dominio\CausaCancelacionOrden;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\AplicacionesCompletas;
use App\Dominios\Operaciones\Dominio\Excepciones\ContratoConOrdenAbierta;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesOrden;
use App\Dominios\Operaciones\Dominio\NumeracionAplicaciones;

/*
 * Tabla de transiciones de `orden de aplicación` (invariante 7 de CLAUDE.md) y
 * regla de numeración correlativa (ADR 0022). Puro, sin Eloquent ni DB — mismo
 * patrón que tests/Unit/MaquinaEstadosComercialTest.php.
 */

test('orden: emitida solo puede pasar a vigente', function () {
    expect(TransicionesOrden::permitida(EstadoOrdenAplicacion::Emitida, EstadoOrdenAplicacion::Vigente))->toBeTrue()
        ->and(TransicionesOrden::permitida(EstadoOrdenAplicacion::Emitida, EstadoOrdenAplicacion::Pausada))->toBeFalse()
        ->and(TransicionesOrden::permitida(EstadoOrdenAplicacion::Emitida, EstadoOrdenAplicacion::Consumida))->toBeFalse()
        ->and(TransicionesOrden::permitida(EstadoOrdenAplicacion::Emitida, EstadoOrdenAplicacion::Cancelada))->toBeFalse();
});

test('orden: vigente puede pausarse, cerrarse o cancelarse', function () {
    expect(TransicionesOrden::permitida(EstadoOrdenAplicacion::Vigente, EstadoOrdenAplicacion::Pausada))->toBeTrue()
        ->and(TransicionesOrden::permitida(EstadoOrdenAplicacion::Vigente, EstadoOrdenAplicacion::Consumida))->toBeTrue()
        ->and(TransicionesOrden::permitida(EstadoOrdenAplicacion::Vigente, EstadoOrdenAplicacion::Cancelada))->toBeTrue()
        ->and(TransicionesOrden::permitida(EstadoOrdenAplicacion::Vigente, EstadoOrdenAplicacion::Emitida))->toBeFalse();
});

test('orden: pausada vuelve a vigente o se cancela, pero no se cierra', function () {
    expect(TransicionesOrden::permitida(EstadoOrdenAplicacion::Pausada, EstadoOrdenAplicacion::Vigente))->toBeTrue()
        ->and(TransicionesOrden::permitida(EstadoOrdenAplicacion::Pausada, EstadoOrdenAplicacion::Cancelada))->toBeTrue()
        ->and(TransicionesOrden::permitida(EstadoOrdenAplicacion::Pausada, EstadoOrdenAplicacion::Consumida))->toBeFalse();
});

test('orden: consumida, cancelada y vencida no tienen ninguna salida', function (EstadoOrdenAplicacion $terminal) {
    foreach (EstadoOrdenAplicacion::cases() as $destino) {
        expect(TransicionesOrden::permitida($terminal, $destino))->toBeFalse();
    }
})->with([
    'consumida' => [EstadoOrdenAplicacion::Consumida],
    'cancelada' => [EstadoOrdenAplicacion::Cancelada],
    'vencida' => [EstadoOrdenAplicacion::Vencida],
]);

test('orden: ningún estado se transiciona a sí mismo', function () {
    foreach (EstadoOrdenAplicacion::cases() as $estado) {
        expect(TransicionesOrden::permitida($estado, $estado))->toBeFalse();
    }
});

test('orden: vencida no es destino de ninguna transición (sin disparador de negocio)', function () {
    foreach (EstadoOrdenAplicacion::cases() as $desde) {
        expect(TransicionesOrden::permitida($desde, EstadoOrdenAplicacion::Vencida))->toBeFalse();
    }
});

test('orden: solo emitida, vigente y pausada están abiertas', function () {
    expect(EstadoOrdenAplicacion::Emitida->estaAbierta())->toBeTrue()
        ->and(EstadoOrdenAplicacion::Vigente->estaAbierta())->toBeTrue()
        ->and(EstadoOrdenAplicacion::Pausada->estaAbierta())->toBeTrue()
        ->and(EstadoOrdenAplicacion::Consumida->estaAbierta())->toBeFalse()
        ->and(EstadoOrdenAplicacion::Cancelada->estaAbierta())->toBeFalse()
        ->and(EstadoOrdenAplicacion::Vencida->estaAbierta())->toBeFalse()
        ->and(EstadoOrdenAplicacion::valoresAbiertos())->toBe(['emitida', 'vigente', 'pausada']);
});

test('orden: la causa del cliente y la del dueño consumen el número; el factor externo no', function () {
    expect(CausaCancelacionOrden::Cliente->consumeNumero())->toBeTrue()
        ->and(CausaCancelacionOrden::Dueno->consumeNumero())->toBeTrue()
        ->and(CausaCancelacionOrden::FactorExterno->consumeNumero())->toBeFalse()
        // Los valores que guarda la base (el CHECK y el índice de numeración los repiten).
        ->and(array_map(fn (CausaCancelacionOrden $causa): string => $causa->value, CausaCancelacionOrden::cases()))
        ->toBe(['cliente', 'dueno', 'factor_externo']);
});

/*
 * Numeración correlativa: el número lo calcula el servidor, una sola
 * aplicación abierta por vez, sin pasar de las previstas.
 */

function ordenDeNumeracion(int $nro, EstadoOrdenAplicacion $estado, ?CausaCancelacionOrden $causa = null): array
{
    return ['nro' => $nro, 'estado' => $estado, 'causa' => $causa];
}

test('numeración: un contrato sin órdenes empieza en la aplicación 1', function () {
    expect(NumeracionAplicaciones::siguiente([], 3, 1))->toBe(1);
});

test('numeración: cada orden cerrada hace avanzar al número siguiente', function () {
    $ordenes = [ordenDeNumeracion(1, EstadoOrdenAplicacion::Consumida), ordenDeNumeracion(2, EstadoOrdenAplicacion::Consumida)];

    expect(NumeracionAplicaciones::siguiente($ordenes, 3, 1))->toBe(3);
});

test('numeración: una cancelada por causa del cliente consume su número', function () {
    $ordenes = [
        ordenDeNumeracion(1, EstadoOrdenAplicacion::Consumida),
        ordenDeNumeracion(2, EstadoOrdenAplicacion::Cancelada, CausaCancelacionOrden::Cliente),
    ];

    expect(NumeracionAplicaciones::siguiente($ordenes, 3, 1))->toBe(3);
});

test('numeración: una cancelada por factor externo se rehace con el mismo número', function () {
    $ordenes = [
        ordenDeNumeracion(1, EstadoOrdenAplicacion::Consumida),
        ordenDeNumeracion(2, EstadoOrdenAplicacion::Cancelada, CausaCancelacionOrden::FactorExterno),
    ];

    expect(NumeracionAplicaciones::siguiente($ordenes, 3, 1))->toBe(2);
});

test('numeración: varias canceladas por factor externo del mismo número no lo consumen', function () {
    $ordenes = [
        ordenDeNumeracion(1, EstadoOrdenAplicacion::Consumida),
        ordenDeNumeracion(2, EstadoOrdenAplicacion::Cancelada, CausaCancelacionOrden::FactorExterno),
        ordenDeNumeracion(2, EstadoOrdenAplicacion::Cancelada, CausaCancelacionOrden::FactorExterno),
    ];

    expect(NumeracionAplicaciones::siguiente($ordenes, 2, 1))->toBe(2);
});

test('numeración: con una aplicación abierta no se numera otra', function (EstadoOrdenAplicacion $abierta) {
    $ordenes = [ordenDeNumeracion(1, EstadoOrdenAplicacion::Consumida), ordenDeNumeracion(2, $abierta)];

    expect(fn () => NumeracionAplicaciones::siguiente($ordenes, 5, 1))->toThrow(ContratoConOrdenAbierta::class);
})->with([
    'emitida' => [EstadoOrdenAplicacion::Emitida],
    'vigente' => [EstadoOrdenAplicacion::Vigente],
    'pausada' => [EstadoOrdenAplicacion::Pausada],
]);

test('numeración: no pasa de las aplicaciones previstas del contrato', function () {
    $ordenes = [
        ordenDeNumeracion(1, EstadoOrdenAplicacion::Consumida),
        ordenDeNumeracion(2, EstadoOrdenAplicacion::Consumida),
        ordenDeNumeracion(3, EstadoOrdenAplicacion::Consumida),
    ];

    expect(fn () => NumeracionAplicaciones::siguiente($ordenes, 3, 1))->toThrow(AplicacionesCompletas::class);
});

test('numeración: la última cancelada por el cliente agota las previstas', function () {
    $ordenes = [
        ordenDeNumeracion(1, EstadoOrdenAplicacion::Consumida),
        ordenDeNumeracion(2, EstadoOrdenAplicacion::Cancelada, CausaCancelacionOrden::Cliente),
    ];

    expect(fn () => NumeracionAplicaciones::siguiente($ordenes, 2, 1))->toThrow(AplicacionesCompletas::class);
});

test('numeración: la última cancelada por factor externo deja rehacerla', function () {
    $ordenes = [
        ordenDeNumeracion(1, EstadoOrdenAplicacion::Consumida),
        ordenDeNumeracion(2, EstadoOrdenAplicacion::Cancelada, CausaCancelacionOrden::FactorExterno),
    ];

    expect(NumeracionAplicaciones::siguiente($ordenes, 2, 1))->toBe(2);
});
