<?php

use App\Dominios\Mantenimiento\Aplicacion\ActualizarBateria;
use App\Dominios\Mantenimiento\Aplicacion\CrearBateria;
use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Mantenimiento\Dominio\Excepciones\BateriaDuplicada;
use App\Dominios\Mantenimiento\Dominio\Excepciones\CorreccionCiclosNoAutorizada;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-83 (tarea 98): `ciclos_inicial` (punto de partida del historial,
 * independiente de `ciclos_acumulados`) y el estado `mantenimiento`.
 * Casos de uso probados directo (sin HTTP) — mismo criterio que
 * tests/Feature/Mantenimiento/CasosDeUsoFichaDronTest.php; la capa HTTP
 * (Request + controlador) la cubre
 * tests/Feature/Mantenimiento/GestionBateriasPanelTest.php.
 */

uses(RefreshDatabase::class);

it('da de alta una batería con ciclo inicial y acumulado independientes', function () {
    $bateria = (new CrearBateria)->ejecutar(
        identificador: 'BAT-001',
        ciclosInicial: 40,
        ciclosAcumulados: 120,
        baseId: null,
        estado: EstadoBateria::Activa,
    );

    expect($bateria->ciclos_inicial)->toBe(40)
        ->and($bateria->ciclos_acumulados)->toBe(120);
});

it('da de alta una batería con ciclo inicial y acumulado iguales, arrancando en 0', function () {
    $bateria = (new CrearBateria)->ejecutar(
        identificador: 'BAT-002',
        ciclosInicial: 0,
        ciclosAcumulados: 0,
        baseId: null,
        estado: EstadoBateria::Activa,
    );

    expect($bateria->ciclos_inicial)->toBe(0)
        ->and($bateria->ciclos_acumulados)->toBe(0);
});

it('da de alta una batería en estado mantenimiento', function () {
    $bateria = (new CrearBateria)->ejecutar(
        identificador: 'BAT-003',
        ciclosInicial: 10,
        ciclosAcumulados: 10,
        baseId: null,
        estado: EstadoBateria::Mantenimiento,
    );

    expect($bateria->estado)->toBe('mantenimiento');
});

it('el identificador duplicado entre baterías activas lanza BateriaDuplicada', function () {
    Bateria::query()->create(['identificador' => 'BAT-001', 'ciclos_inicial' => 0, 'estado' => 'activa']);

    expect(fn () => (new CrearBateria)->ejecutar(
        identificador: 'BAT-001',
        ciclosInicial: 0,
        ciclosAcumulados: 0,
        baseId: null,
        estado: EstadoBateria::Activa,
    ))->toThrow(BateriaDuplicada::class);
});

it('ActualizarBateria::ejecutar() con un ciclos_acumulados nuevo no modifica ciclos_inicial', function () {
    $bateria = Bateria::query()->create([
        'identificador' => 'BAT-001',
        'ciclos_inicial' => 40,
        'ciclos_acumulados' => 40,
        'estado' => 'activa',
    ]);

    $editada = (new ActualizarBateria)->ejecutar(
        $bateria,
        identificador: 'BAT-001',
        ciclosAcumulados: 300,
        baseId: null,
        estado: EstadoBateria::Activa,
    );

    expect($editada->ciclos_acumulados)->toBe(300)
        ->and($editada->ciclos_inicial)->toBe(40);

    // Releída de la base, no solo desde la instancia en memoria.
    expect(Bateria::query()->findOrFail($bateria->id)->ciclos_inicial)->toBe(40);
});

it('ActualizarBateria::ejecutar() acepta la transición hacia y desde el estado mantenimiento', function () {
    $bateria = Bateria::query()->create(['identificador' => 'BAT-001', 'ciclos_inicial' => 0, 'estado' => 'activa']);

    $editada = (new ActualizarBateria)->ejecutar(
        $bateria,
        identificador: 'BAT-001',
        ciclosAcumulados: 0,
        baseId: null,
        estado: EstadoBateria::Mantenimiento,
    );

    expect($editada->estado)->toBe('mantenimiento');
});

/*
 * HU-87 (tarea 102): "odómetro" de ciclos_acumulados — sube solo con cada
 * recarga real, bajarlo a mano sin dejar rastro queda bloqueado salvo que
 * se declare el motivo explícitamente.
 */

it('ActualizarBateria::ejecutar() rechaza una baja de ciclos_acumulados sin motivoCorreccion', function () {
    $bateria = Bateria::query()->create(['identificador' => 'BAT-001', 'ciclos_inicial' => 0, 'ciclos_acumulados' => 100, 'estado' => 'activa']);

    expect(fn () => (new ActualizarBateria)->ejecutar(
        $bateria,
        identificador: 'BAT-001',
        ciclosAcumulados: 80,
        baseId: null,
        estado: EstadoBateria::Activa,
    ))->toThrow(CorreccionCiclosNoAutorizada::class);

    expect(Bateria::query()->findOrFail($bateria->id)->ciclos_acumulados)->toBe(100);
});

it('ActualizarBateria::ejecutar() acepta una baja de ciclos_acumulados con motivoCorreccion', function () {
    $bateria = Bateria::query()->create(['identificador' => 'BAT-001', 'ciclos_inicial' => 0, 'ciclos_acumulados' => 100, 'estado' => 'activa']);

    $editada = (new ActualizarBateria)->ejecutar(
        $bateria,
        identificador: 'BAT-001',
        ciclosAcumulados: 80,
        baseId: null,
        estado: EstadoBateria::Activa,
        motivoCorreccion: 'Ciclos cargados de más por error de tipeo en el alta.',
    );

    expect($editada->ciclos_acumulados)->toBe(80);
});

it('ActualizarBateria::ejecutar() con un motivoCorreccion vacío se trata igual que sin motivo', function () {
    $bateria = Bateria::query()->create(['identificador' => 'BAT-001', 'ciclos_inicial' => 0, 'ciclos_acumulados' => 100, 'estado' => 'activa']);

    expect(fn () => (new ActualizarBateria)->ejecutar(
        $bateria,
        identificador: 'BAT-001',
        ciclosAcumulados: 80,
        baseId: null,
        estado: EstadoBateria::Activa,
        motivoCorreccion: '',
    ))->toThrow(CorreccionCiclosNoAutorizada::class);
});

it('ActualizarBateria::ejecutar() no exige motivoCorreccion cuando ciclos_acumulados queda igual o sube', function () {
    $bateria = Bateria::query()->create(['identificador' => 'BAT-001', 'ciclos_inicial' => 0, 'ciclos_acumulados' => 100, 'estado' => 'activa']);

    $editada = (new ActualizarBateria)->ejecutar(
        $bateria,
        identificador: 'BAT-001',
        ciclosAcumulados: 100,
        baseId: null,
        estado: EstadoBateria::Activa,
    );

    expect($editada->ciclos_acumulados)->toBe(100);
});
