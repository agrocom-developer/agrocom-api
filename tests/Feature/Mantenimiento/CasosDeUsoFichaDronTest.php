<?php

use App\Dominios\Mantenimiento\Aplicacion\ActualizarFichaDron;
use App\Dominios\Mantenimiento\Aplicacion\CrearFichaDron;
use App\Dominios\Mantenimiento\Aplicacion\EliminarFichaDron;
use App\Dominios\Mantenimiento\Dominio\Excepciones\FichaDronDuplicada;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\FichaDron;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-82 (tarea 97): ficha de inventario del dron — serie, chasis, versión de
 * software, región, serie del control y accesorios. Casos de uso probados
 * directo (sin HTTP): la validación cruzada contra `ope_drones` vive en el
 * Request, no acá (ver tests/Feature/Mantenimiento/GestionFichasDronPanelTest.php
 * para esa capa).
 */

uses(RefreshDatabase::class);

it('da de alta una ficha de dron con todos sus datos', function () {
    $ficha = (new CrearFichaDron)->ejecutar(
        identificadorDron: 'DRN-001',
        numeroSerie: 'SN-12345',
        chasis: 'CH-987',
        versionSoftware: '4.2.1',
        region: 'AME',
        serieControl: 'CTRL-555',
        tieneCargadorControl: true,
        tieneModem: false,
        tieneMaletin: true,
    );

    expect($ficha->identificador_dron)->toBe('DRN-001')
        ->and($ficha->numero_serie)->toBe('SN-12345')
        ->and($ficha->chasis)->toBe('CH-987')
        ->and($ficha->version_software)->toBe('4.2.1')
        ->and($ficha->region)->toBe('AME')
        ->and($ficha->serie_control)->toBe('CTRL-555')
        ->and($ficha->tiene_cargador_control)->toBeTrue()
        ->and($ficha->tiene_modem)->toBeFalse()
        ->and($ficha->tiene_maletin)->toBeTrue();
});

it('da de alta una ficha de dron con solo el identificador', function () {
    $ficha = (new CrearFichaDron)->ejecutar(
        identificadorDron: 'DRN-002',
        numeroSerie: null,
        chasis: null,
        versionSoftware: null,
        region: null,
        serieControl: null,
        tieneCargadorControl: false,
        tieneModem: false,
        tieneMaletin: false,
    );

    expect($ficha->numero_serie)->toBeNull()
        ->and($ficha->tiene_cargador_control)->toBeFalse();
});

it('el identificador duplicado entre fichas activas lanza FichaDronDuplicada', function () {
    FichaDron::query()->create(['identificador_dron' => 'DRN-001']);

    expect(fn () => (new CrearFichaDron)->ejecutar(
        identificadorDron: 'DRN-001',
        numeroSerie: null,
        chasis: null,
        versionSoftware: null,
        region: null,
        serieControl: null,
        tieneCargadorControl: false,
        tieneModem: false,
        tieneMaletin: false,
    ))->toThrow(FichaDronDuplicada::class);

    expect(FichaDron::query()->where('identificador_dron', 'DRN-001')->count())->toBe(1);
});

it('una ficha dada de baja no bloquea el re-alta con el mismo identificador', function () {
    $existente = FichaDron::query()->create(['identificador_dron' => 'DRN-001']);
    $existente->delete();

    $nueva = (new CrearFichaDron)->ejecutar(
        identificadorDron: 'DRN-001',
        numeroSerie: null,
        chasis: null,
        versionSoftware: null,
        region: null,
        serieControl: null,
        tieneCargadorControl: false,
        tieneModem: false,
        tieneMaletin: false,
    );

    expect($nueva->exists)->toBeTrue()
        ->and(FichaDron::query()->where('identificador_dron', 'DRN-001')->count())->toBe(1);
});

it('edita una ficha existente, incluido su identificador', function () {
    $ficha = FichaDron::query()->create(['identificador_dron' => 'DRN-001', 'numero_serie' => 'SN-OLD']);

    $editada = (new ActualizarFichaDron)->ejecutar(
        $ficha,
        identificadorDron: 'DRN-001-B',
        numeroSerie: 'SN-NEW',
        chasis: 'CH-NEW',
        versionSoftware: '5.0.0',
        region: 'EUR',
        serieControl: 'CTRL-NEW',
        tieneCargadorControl: true,
        tieneModem: true,
        tieneMaletin: true,
    );

    expect($editada->identificador_dron)->toBe('DRN-001-B')
        ->and($editada->numero_serie)->toBe('SN-NEW')
        ->and($editada->tiene_modem)->toBeTrue();
});

it('editar una ficha hacia un identificador ya usado por otra ficha activa lanza FichaDronDuplicada', function () {
    FichaDron::query()->create(['identificador_dron' => 'DRN-001']);
    $otra = FichaDron::query()->create(['identificador_dron' => 'DRN-002']);

    expect(fn () => (new ActualizarFichaDron)->ejecutar(
        $otra,
        identificadorDron: 'DRN-001',
        numeroSerie: null,
        chasis: null,
        versionSoftware: null,
        region: null,
        serieControl: null,
        tieneCargadorControl: false,
        tieneModem: false,
        tieneMaletin: false,
    ))->toThrow(FichaDronDuplicada::class);
});

it('da de baja por soft delete: la fila sigue existiendo con withTrashed', function () {
    $ficha = FichaDron::query()->create(['identificador_dron' => 'DRN-001']);

    (new EliminarFichaDron)->ejecutar($ficha);

    expect(FichaDron::query()->where('identificador_dron', 'DRN-001')->exists())->toBeFalse();

    $borrada = FichaDron::withTrashed()->findOrFail($ficha->id);
    expect($borrada->trashed())->toBeTrue();
});
