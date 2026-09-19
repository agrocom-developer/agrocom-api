<?php

use App\Dominios\Operaciones\Dominio\CausaCancelacionOrden;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\CorreccionOrdenNoPermitida;
use App\Dominios\Operaciones\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Operaciones\Dominio\PoliticaEdicionOrden;

/*
 * Regla de edición de una orden (ADR 0022, adenda del 19/9/2026): se corrige una
 * orden ABIERTA —emitida, vigente o pausada—; sobre una ya publicada la corrección
 * pide motivo; y con trabajos el insumo (categoría y dosis) no se cambia. Pura,
 * sin app ni DB.
 */

test('edición: se corrige una orden abierta y una cerrada ya es historia', function () {
    foreach ([EstadoOrdenAplicacion::Emitida, EstadoOrdenAplicacion::Vigente, EstadoOrdenAplicacion::Pausada] as $abierta) {
        expect(PoliticaEdicionOrden::admiteEdicion($abierta))->toBeTrue();
    }

    foreach ([EstadoOrdenAplicacion::Consumida, EstadoOrdenAplicacion::Cancelada, EstadoOrdenAplicacion::Vencida] as $cerrada) {
        expect(PoliticaEdicionOrden::admiteEdicion($cerrada))->toBeFalse();
    }
});

test('edición: solo una orden ya publicada pide motivo; mientras es emitida no', function () {
    expect(PoliticaEdicionOrden::exigeMotivo(EstadoOrdenAplicacion::Emitida))->toBeFalse()
        ->and(PoliticaEdicionOrden::exigeMotivo(EstadoOrdenAplicacion::Vigente))->toBeTrue()
        ->and(PoliticaEdicionOrden::exigeMotivo(EstadoOrdenAplicacion::Pausada))->toBeTrue()
        // Una cerrada no se edita, así que tampoco hay motivo que pedir.
        ->and(PoliticaEdicionOrden::exigeMotivo(EstadoOrdenAplicacion::Consumida))->toBeFalse();
});

test('edición: el insumo se bloquea en cuanto la orden tiene trabajos', function () {
    expect(PoliticaEdicionOrden::bloqueaInsumo(tieneTrabajos: true))->toBeTrue()
        ->and(PoliticaEdicionOrden::bloqueaInsumo(tieneTrabajos: false))->toBeFalse();
});

test('edición: cambiar la categoría o la dosis cuenta como cambiar el insumo', function () {
    $actual = ['categoria_insumo_id' => 3, 'kilos_por_vuelo' => null, 'litros_ha' => '10.00'];

    expect(PoliticaEdicionOrden::cambiaInsumo($actual, ['categoria_insumo_id' => 4, 'litros_ha' => '10.00']))->toBeTrue()
        ->and(PoliticaEdicionOrden::cambiaInsumo($actual, ['categoria_insumo_id' => 3, 'litros_ha' => '12.50']))->toBeTrue()
        // De líquido a sólido: aparece una dosis que no había y desaparece la otra.
        ->and(PoliticaEdicionOrden::cambiaInsumo($actual, ['categoria_insumo_id' => 3, 'kilos_por_vuelo' => '5.00', 'litros_ha' => null]))->toBeTrue();
});

test('edición: lo que no cambia el insumo no lo cuenta, aunque venga escrito distinto', function () {
    $actual = ['categoria_insumo_id' => 3, 'kilos_por_vuelo' => null, 'litros_ha' => '10.00'];

    expect(PoliticaEdicionOrden::cambiaInsumo($actual, ['categoria_insumo_id' => '3', 'litros_ha' => '10']))->toBeFalse()
        ->and(PoliticaEdicionOrden::cambiaInsumo($actual, ['categoria_insumo_id' => 3, 'litros_ha' => '10.000', 'kilos_por_vuelo' => '']))->toBeFalse()
        // Las claves de insumo que no vienen no se tocan.
        ->and(PoliticaEdicionOrden::cambiaInsumo($actual, ['observaciones' => 'otra cosa']))->toBeFalse();
});

test('edición: los mensajes de las excepciones salen del idioma y existen en lang/es', function () {
    expect(MotivoRequerido::paraCorregir()->getMessage())->toBe('operaciones.errores.motivo_correccion_requerido')
        ->and(CorreccionOrdenNoPermitida::insumoConTrabajos()->getMessage())->toBe('operaciones.errores.correccion_insumo_con_trabajos');

    /** @var array{ordenes: array<string, mixed>, errores: array<string, string>} $lang */
    $lang = require dirname(__DIR__, 2).'/lang/es/operaciones.php';

    expect($lang['errores'])->toHaveKey('motivo_correccion_requerido')->toHaveKey('correccion_insumo_con_trabajos');

    foreach (['campo_motivo_correccion', 'campo_motivo_correccion_placeholder', 'error_motivo_correccion_requerido', 'correccion_aviso', 'insumo_bloqueado_ayuda'] as $clave) {
        expect($lang['ordenes'])->toHaveKey($clave);
    }
});

test('cancelación: cada causa tiene su texto en lang/es', function () {
    /** @var array{ordenes: array<string, mixed>} $lang */
    $lang = require dirname(__DIR__, 2).'/lang/es/operaciones.php';

    foreach (CausaCancelacionOrden::cases() as $causa) {
        expect($lang['ordenes'])->toHaveKey('causa_'.$causa->value);
    }

    expect($lang['ordenes'])->toHaveKey('ayuda_causa_cancelacion');
});
