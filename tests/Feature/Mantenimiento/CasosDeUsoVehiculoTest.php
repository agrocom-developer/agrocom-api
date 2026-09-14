<?php

use App\Dominios\Mantenimiento\Aplicacion\ActualizarVehiculo;
use App\Dominios\Mantenimiento\Aplicacion\CrearVehiculo;
use App\Dominios\Mantenimiento\Dominio\EstadoVehiculo;
use App\Dominios\Mantenimiento\Dominio\Excepciones\VehiculoDuplicado;
use App\Dominios\Mantenimiento\Dominio\TipoCombustibleVehiculo;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-84 (tarea 99): ficha completa de vehículo (marca, modelo, año,
 * combustible, 4x4, kilometraje inicial y actual) y el estado `pausa`.
 * Casos de uso probados directo (sin HTTP) — mismo criterio que
 * tests/Feature/Mantenimiento/CasosDeUsoBateriaTest.php; la capa HTTP
 * (Request + controlador) la cubre
 * tests/Feature/Mantenimiento/GestionVehiculosPanelTest.php.
 *
 * A diferencia de `ciclos_inicial` en baterías (tarea 98),
 * `kilometraje_inicial` NO es inmutable: `ActualizarVehiculo` lo recibe
 * igual que `CrearVehiculo`, sin restricción.
 */

uses(RefreshDatabase::class);

it('da de alta un vehículo con la ficha completa de inventario', function () {
    $vehiculo = (new CrearVehiculo)->ejecutar(
        identificador: 'VHC-001',
        baseId: null,
        estado: EstadoVehiculo::Activo,
        marca: 'Toyota',
        modelo: 'Hilux',
        anio: 2022,
        combustible: TipoCombustibleVehiculo::Diesel,
        es4x4: true,
        kilometrajeInicial: '1000.00',
        kilometrajeActual: '1500.50',
    );

    expect($vehiculo->marca)->toBe('Toyota')
        ->and($vehiculo->modelo)->toBe('Hilux')
        ->and($vehiculo->anio)->toBe(2022)
        ->and($vehiculo->combustible)->toBe('diesel')
        ->and($vehiculo->es_4x4)->toBeTrue()
        ->and((string) $vehiculo->kilometraje_inicial)->toBe('1000.00')
        ->and((string) $vehiculo->kilometraje_actual)->toBe('1500.50');
});

it('da de alta un vehículo sin datos de ficha, con los valores por defecto', function () {
    $vehiculo = (new CrearVehiculo)->ejecutar(
        identificador: 'VHC-002',
        baseId: null,
        estado: EstadoVehiculo::Activo,
        marca: null,
        modelo: null,
        anio: null,
        combustible: null,
        es4x4: false,
        kilometrajeInicial: null,
        kilometrajeActual: null,
    );

    expect($vehiculo->marca)->toBeNull()
        ->and($vehiculo->modelo)->toBeNull()
        ->and($vehiculo->anio)->toBeNull()
        ->and($vehiculo->combustible)->toBeNull()
        ->and($vehiculo->es_4x4)->toBeFalse()
        ->and($vehiculo->kilometraje_inicial)->toBeNull()
        ->and($vehiculo->kilometraje_actual)->toBeNull();
});

it('da de alta un vehículo en estado pausa', function () {
    $vehiculo = (new CrearVehiculo)->ejecutar(
        identificador: 'VHC-003',
        baseId: null,
        estado: EstadoVehiculo::Pausa,
        marca: null,
        modelo: null,
        anio: null,
        combustible: null,
        es4x4: false,
        kilometrajeInicial: null,
        kilometrajeActual: null,
    );

    expect($vehiculo->estado)->toBe('pausa');
});

it('el identificador duplicado entre vehículos activos lanza VehiculoDuplicado', function () {
    Vehiculo::query()->create(['identificador' => 'VHC-001', 'estado' => 'activo']);

    expect(fn () => (new CrearVehiculo)->ejecutar(
        identificador: 'VHC-001',
        baseId: null,
        estado: EstadoVehiculo::Activo,
        marca: null,
        modelo: null,
        anio: null,
        combustible: null,
        es4x4: false,
        kilometrajeInicial: null,
        kilometrajeActual: null,
    ))->toThrow(VehiculoDuplicado::class);
});

it('ActualizarVehiculo::ejecutar() edita el kilometraje inicial, a diferencia de ciclos_inicial en baterías', function () {
    $vehiculo = Vehiculo::query()->create([
        'identificador' => 'VHC-001',
        'estado' => 'activo',
        'kilometraje_inicial' => '1000.00',
        'kilometraje_actual' => '1000.00',
    ]);

    $editado = (new ActualizarVehiculo)->ejecutar(
        $vehiculo,
        identificador: 'VHC-001',
        baseId: null,
        estado: EstadoVehiculo::Activo,
        marca: null,
        modelo: null,
        anio: null,
        combustible: null,
        es4x4: false,
        kilometrajeInicial: '500.00',
        kilometrajeActual: '2000.00',
    );

    expect((string) $editado->kilometraje_inicial)->toBe('500.00')
        ->and((string) $editado->kilometraje_actual)->toBe('2000.00');

    // Releído de la base, no solo desde la instancia en memoria.
    expect((string) Vehiculo::query()->findOrFail($vehiculo->id)->kilometraje_inicial)->toBe('500.00');
});

it('ActualizarVehiculo::ejecutar() acepta la transición hacia y desde el estado pausa', function () {
    $vehiculo = Vehiculo::query()->create(['identificador' => 'VHC-001', 'estado' => 'activo']);

    $editado = (new ActualizarVehiculo)->ejecutar(
        $vehiculo,
        identificador: 'VHC-001',
        baseId: null,
        estado: EstadoVehiculo::Pausa,
        marca: null,
        modelo: null,
        anio: null,
        combustible: null,
        es4x4: false,
        kilometrajeInicial: null,
        kilometrajeActual: null,
    );

    expect($editado->estado)->toBe('pausa');

    $reactivado = (new ActualizarVehiculo)->ejecutar(
        $editado,
        identificador: 'VHC-001',
        baseId: null,
        estado: EstadoVehiculo::Activo,
        marca: null,
        modelo: null,
        anio: null,
        combustible: null,
        es4x4: false,
        kilometrajeInicial: null,
        kilometrajeActual: null,
    );

    expect($reactivado->estado)->toBe('activo');
});
