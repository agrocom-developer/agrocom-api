<?php

use App\Dominios\Compartido\Dominio\TipoDocumento;
use App\Dominios\Proveedores\Dominio\RequisitosDocumentales;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Dominio\VencimientoDocumento;

/*
 * Política de verificación de proveedores (docs/politicas/verificacion_proveedores.md,
 * ADR 0032): qué documentos exige cada sujeto, cuándo está vigente uno y cuándo
 * avisa el proceso diario. Puro: las fechas llegan como parámetro.
 */

function hoyDeLaPrueba(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-11-23 15:30:00');
}

test('cada sujeto exige sus documentos obligatorios', function () {
    $requisitos = new RequisitosDocumentales;

    expect($requisitos->paraSujeto(TipoSujeto::Piloto))->toBe([TipoDocumento::Identidad, TipoDocumento::LicenciaPiloto, TipoDocumento::Antecedentes])
        ->and($requisitos->paraSujeto(TipoSujeto::Dron))->toBe([TipoDocumento::RegistroDron, TipoDocumento::HabilitacionDron, TipoDocumento::SeguroResponsabilidad])
        ->and($requisitos->paraSujeto(TipoSujeto::Auxiliar))->toBe([TipoDocumento::Identidad, TipoDocumento::Antecedentes])
        ->and($requisitos->paraSujeto(TipoSujeto::Proveedor))->toBe([TipoDocumento::Identidad, TipoDocumento::SeguroResponsabilidad]);
});

test('todo requisito por defecto es un tipo de documento que existe', function () {
    foreach (RequisitosDocumentales::POR_DEFECTO as $sujeto => $tipos) {
        expect(TipoSujeto::tryFrom($sujeto))->not->toBeNull();

        foreach ($tipos as $tipo) {
            expect(TipoDocumento::tryFrom($tipo))->not->toBeNull();
        }
    }
});

test('solo un documento aprobado y no vencido cuenta como vigente', function () {
    $hoy = hoyDeLaPrueba();

    expect(RequisitosDocumentales::estaVigente('aprobado', null, $hoy))->toBeTrue()
        ->and(RequisitosDocumentales::estaVigente('aprobado', '2026-11-23', $hoy))->toBeTrue()
        ->and(RequisitosDocumentales::estaVigente('aprobado', '2026-11-22', $hoy))->toBeFalse()
        ->and(RequisitosDocumentales::estaVigente('en_revision', '2027-01-01', $hoy))->toBeFalse()
        ->and(RequisitosDocumentales::estaVigente('rechazado', null, $hoy))->toBeFalse()
        ->and(RequisitosDocumentales::estaVigente('vencido', null, $hoy))->toBeFalse();
});

test('faltan los obligatorios que no tienen un documento aprobado y vigente', function () {
    $requisitos = new RequisitosDocumentales;

    $faltantes = $requisitos->faltantes(TipoSujeto::Piloto, [
        ['tipo' => 'identidad', 'estado' => 'aprobado', 'vence_en' => null],
        ['tipo' => 'licencia_piloto', 'estado' => 'aprobado', 'vence_en' => '2026-11-01'],
        ['tipo' => 'antecedentes', 'estado' => 'en_revision', 'vence_en' => null],
    ], hoyDeLaPrueba());

    expect($faltantes)->toBe([TipoDocumento::LicenciaPiloto, TipoDocumento::Antecedentes]);
});

test('un documento nuevo vigente reemplaza al vencido del mismo tipo', function () {
    $requisitos = new RequisitosDocumentales;

    $faltantes = $requisitos->faltantes(TipoSujeto::Dron, [
        ['tipo' => 'registro_dron', 'estado' => 'aprobado', 'vence_en' => null],
        ['tipo' => 'habilitacion_dron', 'estado' => 'aprobado', 'vence_en' => null],
        ['tipo' => 'seguro_responsabilidad', 'estado' => 'vencido', 'vence_en' => '2026-11-22'],
        ['tipo' => 'seguro_responsabilidad', 'estado' => 'aprobado', 'vence_en' => '2027-11-22'],
    ], hoyDeLaPrueba());

    expect($faltantes)->toBe([]);
});

test('los requisitos son configurables sin tocar el dominio', function () {
    $requisitos = new RequisitosDocumentales(['piloto' => ['identidad']]);

    expect($requisitos->paraSujeto(TipoSujeto::Piloto))->toBe([TipoDocumento::Identidad])
        ->and($requisitos->paraSujeto(TipoSujeto::Dron))->toBe([]);
});

test('el vencimiento se clasifica por días calendario, sin que importe la hora del día', function () {
    $hoy = hoyDeLaPrueba();

    expect(VencimientoDocumento::clasificar(null, $hoy))->toBe(VencimientoDocumento::Vigente)
        ->and(VencimientoDocumento::clasificar('2026-11-22', $hoy))->toBe(VencimientoDocumento::Vencido)
        ->and(VencimientoDocumento::clasificar('2026-11-23', $hoy))->toBe(VencimientoDocumento::PorVencer7)
        ->and(VencimientoDocumento::clasificar('2026-11-30', $hoy))->toBe(VencimientoDocumento::PorVencer7)
        ->and(VencimientoDocumento::clasificar('2026-12-01', $hoy))->toBe(VencimientoDocumento::PorVencer30)
        ->and(VencimientoDocumento::clasificar('2026-12-23', $hoy))->toBe(VencimientoDocumento::PorVencer30)
        ->and(VencimientoDocumento::clasificar('2026-12-24', $hoy))->toBe(VencimientoDocumento::Vigente);
});

test('los días hasta el vencimiento cuentan desde el inicio de hoy', function () {
    expect(VencimientoDocumento::diasHasta('2026-11-23', hoyDeLaPrueba()))->toBe(0)
        ->and(VencimientoDocumento::diasHasta('2026-12-23', hoyDeLaPrueba()))->toBe(30)
        ->and(VencimientoDocumento::diasHasta('2026-11-20', hoyDeLaPrueba()))->toBe(-3);
});
