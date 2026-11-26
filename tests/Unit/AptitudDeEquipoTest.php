<?php

use App\Dominios\Flota\Dominio\AptitudDeEquipo;
use App\Dominios\Flota\Dominio\MotivoNoApto;
use App\Dominios\Flota\Dominio\Vigencia;

/*
 * Un equipo (piloto + dron) recibe ofertas solo si todo lo
 * que lo compone está aprobado, con documentos vigentes y dentro de su vigencia
 * (especificación del marketplace §5.2 y §6; ADR 0029). Puro.
 */

function motivosDelEquipo(array $cambios = []): array
{
    $base = [
        'estadoProveedor' => 'aprobado', 'estadoPiloto' => 'aprobado', 'estadoDron' => 'aprobado',
        'documentosPilotoCompletos' => true, 'documentosDronCompletos' => true,
        'vigencia' => new Vigencia('2026-01-01'), 'hoy' => '2026-11-23',
    ];

    return AptitudDeEquipo::motivosDeNoAptitud(...[...$base, ...$cambios]);
}

test('un equipo con todo aprobado, documentos vigentes y vigencia abierta es apto', function () {
    expect(motivosDelEquipo())->toBe([]);
});

test('cada componente no aprobado deja su propio motivo', function () {
    expect(motivosDelEquipo(['estadoProveedor' => 'suspendido']))->toBe([MotivoNoApto::ProveedorNoAprobado])
        ->and(motivosDelEquipo(['estadoPiloto' => 'pendiente']))->toBe([MotivoNoApto::PilotoNoAprobado])
        ->and(motivosDelEquipo(['estadoDron' => 'rechazado']))->toBe([MotivoNoApto::DronNoAprobado]);
});

test('documentos incompletos del piloto o del dron vuelven no apto al equipo', function () {
    expect(motivosDelEquipo(['documentosPilotoCompletos' => false]))->toBe([MotivoNoApto::DocumentosPilotoVencidos])
        ->and(motivosDelEquipo(['documentosDronCompletos' => false]))->toBe([MotivoNoApto::DocumentosDronVencidos]);
});

test('fuera de su vigencia el equipo no es apto, y los motivos se acumulan', function () {
    $motivos = motivosDelEquipo([
        'vigencia' => new Vigencia('2026-01-01', '2026-11-01'),
        'estadoDron' => 'suspendido',
    ]);

    expect($motivos)->toBe([MotivoNoApto::DronNoAprobado, MotivoNoApto::FueraDeVigencia])
        ->and(AptitudDeEquipo::esApto('aprobado', 'aprobado', 'suspendido', true, true, new Vigencia('2026-01-01'), '2026-11-23'))->toBeFalse();
});

test('la vigencia incluye sus dos extremos y una vigencia abierta no termina', function () {
    $acotada = new Vigencia('2026-11-01', '2026-11-30');

    expect($acotada->estaVigenteEl('2026-11-01'))->toBeTrue()
        ->and($acotada->estaVigenteEl('2026-11-30'))->toBeTrue()
        ->and($acotada->estaVigenteEl('2026-10-31'))->toBeFalse()
        ->and($acotada->estaVigenteEl('2026-12-01'))->toBeFalse()
        ->and((new Vigencia('2026-11-01'))->estaVigenteEl('2030-01-01'))->toBeTrue();
});

test('dos vigencias que se tocan en un día solapan, y una abierta solapa con todo lo posterior', function () {
    $noviembre = new Vigencia('2026-11-01', '2026-11-30');

    expect($noviembre->solapaCon(new Vigencia('2026-11-30', '2026-12-15')))->toBeTrue()
        ->and($noviembre->solapaCon(new Vigencia('2026-12-01', '2026-12-15')))->toBeFalse()
        ->and($noviembre->solapaCon(new Vigencia('2026-10-01')))->toBeTrue()
        ->and((new Vigencia('2026-12-01'))->solapaCon($noviembre))->toBeFalse();
});

test('una vigencia que termina antes de empezar no es válida', function () {
    expect((new Vigencia('2026-11-30', '2026-11-01'))->esValida())->toBeFalse()
        ->and((new Vigencia('2026-11-01', '2026-11-01'))->esValida())->toBeTrue()
        ->and((new Vigencia('2026-11-01'))->esValida())->toBeTrue();
});
