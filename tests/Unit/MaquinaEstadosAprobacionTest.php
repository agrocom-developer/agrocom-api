<?php

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\DecisionDeAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Dominio\TransicionesAprobacion;
use App\Dominios\Proveedores\Dominio\EstadoDocumento;
use App\Dominios\Proveedores\Dominio\TransicionesDocumento;

/*
 * Ciclo de aprobación de proveedor, piloto y dron (especificación del
 * marketplace §5.2, ADR 0032, invariante 7 de CLAUDE.md). Pura, sin Eloquent ni
 * base: la tabla de transiciones y la guarda de motivo.
 */

test('pendiente se aprueba, se rechaza o se da de baja, y nada más', function () {
    expect(TransicionesAprobacion::permitida(EstadoAprobacion::Pendiente, EstadoAprobacion::Aprobado))->toBeTrue()
        ->and(TransicionesAprobacion::permitida(EstadoAprobacion::Pendiente, EstadoAprobacion::Rechazado))->toBeTrue()
        ->and(TransicionesAprobacion::permitida(EstadoAprobacion::Pendiente, EstadoAprobacion::Baja))->toBeTrue()
        ->and(TransicionesAprobacion::permitida(EstadoAprobacion::Pendiente, EstadoAprobacion::Suspendido))->toBeFalse();
});

test('aprobado y suspendido van y vienen; un aprobado no vuelve a pendiente', function () {
    expect(TransicionesAprobacion::permitida(EstadoAprobacion::Aprobado, EstadoAprobacion::Suspendido))->toBeTrue()
        ->and(TransicionesAprobacion::permitida(EstadoAprobacion::Suspendido, EstadoAprobacion::Aprobado))->toBeTrue()
        ->and(TransicionesAprobacion::permitida(EstadoAprobacion::Aprobado, EstadoAprobacion::Pendiente))->toBeFalse()
        ->and(TransicionesAprobacion::permitida(EstadoAprobacion::Aprobado, EstadoAprobacion::Rechazado))->toBeFalse();
});

test('un rechazado solo puede reenviarse a revisión o darse de baja', function () {
    expect(TransicionesAprobacion::permitida(EstadoAprobacion::Rechazado, EstadoAprobacion::Pendiente))->toBeTrue()
        ->and(TransicionesAprobacion::permitida(EstadoAprobacion::Rechazado, EstadoAprobacion::Baja))->toBeTrue()
        ->and(TransicionesAprobacion::permitida(EstadoAprobacion::Rechazado, EstadoAprobacion::Aprobado))->toBeFalse();
});

test('la baja es terminal: ninguna acción es aplicable', function () {
    expect(TransicionesAprobacion::accionesDesde(EstadoAprobacion::Baja))->toBe([]);

    foreach (EstadoAprobacion::cases() as $destino) {
        expect(TransicionesAprobacion::permitida(EstadoAprobacion::Baja, $destino))->toBeFalse();
    }
});

test('aprobar es la decisión sobre un pendiente y rehabilitar la de un suspendido, no intercambiables', function () {
    expect(TransicionesAprobacion::aplicable(EstadoAprobacion::Pendiente, AccionAprobacion::Aprobar))->toBeTrue()
        ->and(TransicionesAprobacion::aplicable(EstadoAprobacion::Suspendido, AccionAprobacion::Aprobar))->toBeFalse()
        ->and(TransicionesAprobacion::aplicable(EstadoAprobacion::Suspendido, AccionAprobacion::Rehabilitar))->toBeTrue()
        ->and(TransicionesAprobacion::aplicable(EstadoAprobacion::Pendiente, AccionAprobacion::Rehabilitar))->toBeFalse();
});

test('las acciones que ofrece el panel salen de la tabla, en orden', function () {
    expect(TransicionesAprobacion::accionesDesde(EstadoAprobacion::Pendiente))->toBe([
        AccionAprobacion::Aprobar, AccionAprobacion::Rechazar, AccionAprobacion::DarDeBaja,
    ])->and(TransicionesAprobacion::accionesDesde(EstadoAprobacion::Aprobado))->toBe([
        AccionAprobacion::Suspender, AccionAprobacion::DarDeBaja,
    ])->and(TransicionesAprobacion::accionesDesde(EstadoAprobacion::Rechazado))->toBe([
        AccionAprobacion::DarDeBaja, AccionAprobacion::Reenviar,
    ]);
});

test('rechazar, suspender y dar de baja exigen motivo; aprobar, rehabilitar y reenviar no', function () {
    foreach ([AccionAprobacion::Rechazar, AccionAprobacion::Suspender, AccionAprobacion::DarDeBaja] as $accion) {
        expect($accion->requiereMotivo())->toBeTrue();
    }

    foreach ([AccionAprobacion::Aprobar, AccionAprobacion::Rehabilitar, AccionAprobacion::Reenviar] as $accion) {
        expect($accion->requiereMotivo())->toBeFalse();
    }
});

test('una decisión sin motivo donde se exige, o fuera de estado, se rechaza', function () {
    expect(fn () => DecisionDeAprobacion::validar(EstadoAprobacion::Pendiente, AccionAprobacion::Rechazar, '   '))
        ->toThrow(MotivoRequerido::class)
        ->and(fn () => DecisionDeAprobacion::validar(EstadoAprobacion::Aprobado, AccionAprobacion::Aprobar, null))
        ->toThrow(TransicionDeAprobacionInvalida::class)
        ->and(fn () => DecisionDeAprobacion::validar(EstadoAprobacion::Baja, AccionAprobacion::Rehabilitar, null))
        ->toThrow(TransicionDeAprobacionInvalida::class);
});

test('una decisión válida devuelve el estado destino y solo conserva el motivo donde corresponde', function () {
    expect(DecisionDeAprobacion::validar(EstadoAprobacion::Pendiente, AccionAprobacion::Aprobar, null))->toBe(EstadoAprobacion::Aprobado)
        ->and(DecisionDeAprobacion::validar(EstadoAprobacion::Aprobado, AccionAprobacion::Suspender, 'Seguro vencido'))->toBe(EstadoAprobacion::Suspendido)
        ->and(DecisionDeAprobacion::motivoAGuardar(AccionAprobacion::Suspender, '  Seguro vencido '))->toBe('Seguro vencido')
        ->and(DecisionDeAprobacion::motivoAGuardar(AccionAprobacion::Aprobar, 'ignorado'))->toBeNull();
});

test('solo el solicitante reenvía a revisión: el resto lo decide un administrador', function () {
    foreach (AccionAprobacion::cases() as $accion) {
        expect($accion->esDelSolicitante())->toBe($accion === AccionAprobacion::Reenviar);
    }
});

test('solo un aprobado puede operar', function () {
    foreach (EstadoAprobacion::cases() as $estado) {
        expect($estado->puedeOperar())->toBe($estado === EstadoAprobacion::Aprobado);
    }
});

test('documento: en revisión se aprueba, se rechaza o vence; un aprobado solo vence; rechazado y vencido son terminales', function () {
    expect(TransicionesDocumento::permitida(EstadoDocumento::EnRevision, EstadoDocumento::Aprobado))->toBeTrue()
        ->and(TransicionesDocumento::permitida(EstadoDocumento::EnRevision, EstadoDocumento::Rechazado))->toBeTrue()
        ->and(TransicionesDocumento::permitida(EstadoDocumento::Aprobado, EstadoDocumento::Vencido))->toBeTrue()
        ->and(TransicionesDocumento::permitida(EstadoDocumento::Aprobado, EstadoDocumento::Rechazado))->toBeFalse()
        ->and(TransicionesDocumento::permitida(EstadoDocumento::Rechazado, EstadoDocumento::Aprobado))->toBeFalse()
        ->and(TransicionesDocumento::permitida(EstadoDocumento::Vencido, EstadoDocumento::Aprobado))->toBeFalse();
});
