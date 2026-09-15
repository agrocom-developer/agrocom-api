<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Operaciones\Contratos\AperturaEstadiaHacienda;
use App\Dominios\Operaciones\Contratos\CierreEstadiaHacienda;
use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Contrato de escritura de `Operaciones` para el motor de sync (ADR 0003,
 * regla 2; HU-51, tarea 74) — probado a nivel de caso de uso, sin pasar por
 * HTTP, mismo patrón que `EscrituraSincronizacionTest.php`. El enchufe en
 * `SincronizarLote::ORDEN_CAUSAL` (tipos `estadia_entrada`/`estadia_salida`)
 * y el test de replay son etapa 2 de esta tarea, no acá.
 */

uses(RefreshDatabase::class);

function equipoTrabajoParaEstadia(): EquipoTrabajo
{
    $base = PerBase::create(['nombre' => 'Base de prueba']);

    return EquipoTrabajo::create([
        'codigo' => 'EQ-'.uniqid(),
        'base_id' => $base->id,
        'estado' => 'activo',
        'desde' => '2026-01-01',
    ]);
}

function propiedadParaEstadia(): Propiedad
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de prueba', 'tipo_persona' => 'juridica']);

    return Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
}

test('abrirEstadia con datos válidos aplica y persiste la fila', function () {
    $equipo = equipoTrabajoParaEstadia();
    $propiedad = propiedadParaEstadia();
    $contrato = app(EscrituraSincronizacion::class);

    $datos = AperturaEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-estadia-1',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T08:00:00-04:00',
    ]);

    $resultado = $contrato->abrirEstadia($datos);

    expect($resultado->estado)->toBe('aplicado')
        ->and($resultado->motivo)->toBeNull();

    expect(EstadiaHacienda::query()->where('uuid_cliente', 'uuid-estadia-1')->exists())->toBeTrue();
});

test('abrirEstadia con vehículo y observación persiste ambos campos', function () {
    $equipo = equipoTrabajoParaEstadia();
    $propiedad = propiedadParaEstadia();
    $vehiculo = Vehiculo::create([
        'identificador' => 'VEH-'.uniqid(),
        'estado' => 'activo',
    ]);
    $contrato = app(EscrituraSincronizacion::class);

    $datos = AperturaEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-estadia-vehiculo',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T08:00:00-04:00',
        'vehiculo_id' => $vehiculo->id,
        'observacion' => 'ingreso por tranquera norte',
    ]);

    $contrato->abrirEstadia($datos);

    $estadia = EstadiaHacienda::query()->where('uuid_cliente', 'uuid-estadia-vehiculo')->first();

    expect($estadia->vehiculo_id)->toBe($vehiculo->id)
        ->and($estadia->observacion)->toBe('ingreso por tranquera norte');
});

test('abrirEstadia reintentando el mismo uuid_cliente devuelve duplicado', function () {
    $equipo = equipoTrabajoParaEstadia();
    $propiedad = propiedadParaEstadia();
    $contrato = app(EscrituraSincronizacion::class);

    $datos = AperturaEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-estadia-reintento',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T08:00:00-04:00',
    ]);

    $contrato->abrirEstadia($datos);
    $resultado = $contrato->abrirEstadia($datos);

    expect($resultado->estado)->toBe('duplicado');
    expect(EstadiaHacienda::query()->where('uuid_cliente', 'uuid-estadia-reintento')->count())->toBe(1);
});

test('abrirEstadia para un equipo con estadía abierta se rechaza sin frenar el uso posterior del contrato', function () {
    $equipo = equipoTrabajoParaEstadia();
    $propiedad = propiedadParaEstadia();
    $contrato = app(EscrituraSincronizacion::class);

    $primera = AperturaEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-estadia-abierta-1',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T08:00:00-04:00',
    ]);
    $contrato->abrirEstadia($primera);

    $segunda = AperturaEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-estadia-abierta-2',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-02T08:00:00-04:00',
    ]);
    $resultado = $contrato->abrirEstadia($segunda);

    expect($resultado->estado)->toBe('rechazado')
        ->and($resultado->motivo)->not->toBeNull();

    expect(EstadiaHacienda::query()->where('uuid_cliente', 'uuid-estadia-abierta-2')->exists())->toBeFalse();

    // El rechazo no deja el contrato en un estado inconsistente: un tercer
    // registro, de otro equipo, se sigue aplicando con normalidad.
    $otroEquipo = equipoTrabajoParaEstadia();
    $tercera = AperturaEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-estadia-otro-equipo',
        'equipo_trabajo_id' => $otroEquipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-02T08:00:00-04:00',
    ]);
    expect($contrato->abrirEstadia($tercera)->estado)->toBe('aplicado');
});

test('cerrarEstadia con datos válidos aplica y persiste la salida', function () {
    $equipo = equipoTrabajoParaEstadia();
    $propiedad = propiedadParaEstadia();
    $contrato = app(EscrituraSincronizacion::class);

    $contrato->abrirEstadia(AperturaEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-estadia-a-cerrar',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T08:00:00-04:00',
    ]));

    $cierre = CierreEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-evento-salida-1',
        'estadia_uuid_cliente' => 'uuid-estadia-a-cerrar',
        'salida' => '2026-09-03T08:00:00-04:00',
    ]);

    $resultado = $contrato->cerrarEstadia($cierre);

    expect($resultado->estado)->toBe('aplicado');

    $estadia = EstadiaHacienda::query()->where('uuid_cliente', 'uuid-estadia-a-cerrar')->first();
    expect($estadia->salida)->not->toBeNull()
        ->and($estadia->cierre_uuid_cliente)->toBe('uuid-evento-salida-1');
});

test('cerrarEstadia sobre una estadía inexistente se rechaza', function () {
    $contrato = app(EscrituraSincronizacion::class);

    $cierre = CierreEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-evento-salida-inexistente',
        'estadia_uuid_cliente' => 'uuid-estadia-que-no-existe',
        'salida' => '2026-09-03T08:00:00-04:00',
    ]);

    $resultado = $contrato->cerrarEstadia($cierre);

    expect($resultado->estado)->toBe('rechazado')
        ->and($resultado->motivo)->not->toBeNull();
});

test('cerrarEstadia reintentando el mismo evento de salida devuelve duplicado', function () {
    $equipo = equipoTrabajoParaEstadia();
    $propiedad = propiedadParaEstadia();
    $contrato = app(EscrituraSincronizacion::class);

    $contrato->abrirEstadia(AperturaEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-estadia-reintento-cierre',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T08:00:00-04:00',
    ]));

    $cierre = CierreEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-evento-salida-repetido',
        'estadia_uuid_cliente' => 'uuid-estadia-reintento-cierre',
        'salida' => '2026-09-03T08:00:00-04:00',
    ]);

    $contrato->cerrarEstadia($cierre);
    $resultado = $contrato->cerrarEstadia($cierre);

    expect($resultado->estado)->toBe('duplicado');
});

test('cerrarEstadia con un segundo evento de salida distinto sobre una estadía ya cerrada se rechaza', function () {
    $equipo = equipoTrabajoParaEstadia();
    $propiedad = propiedadParaEstadia();
    $contrato = app(EscrituraSincronizacion::class);

    $contrato->abrirEstadia(AperturaEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-estadia-doble-cierre',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T08:00:00-04:00',
    ]));

    $contrato->cerrarEstadia(CierreEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-evento-salida-original',
        'estadia_uuid_cliente' => 'uuid-estadia-doble-cierre',
        'salida' => '2026-09-03T08:00:00-04:00',
    ]));

    $resultado = $contrato->cerrarEstadia(CierreEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-evento-salida-otro',
        'estadia_uuid_cliente' => 'uuid-estadia-doble-cierre',
        'salida' => '2026-09-04T08:00:00-04:00',
    ]));

    expect($resultado->estado)->toBe('rechazado')
        ->and($resultado->motivo)->not->toBeNull();
});

test('cerrarEstadia con salida anterior o igual a la entrada se rechaza', function () {
    $equipo = equipoTrabajoParaEstadia();
    $propiedad = propiedadParaEstadia();
    $contrato = app(EscrituraSincronizacion::class);

    $contrato->abrirEstadia(AperturaEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-estadia-fecha-invalida',
        'equipo_trabajo_id' => $equipo->id,
        'propiedad_id' => $propiedad->id,
        'entrada' => '2026-09-01T08:00:00-04:00',
    ]));

    $resultado = $contrato->cerrarEstadia(CierreEstadiaHacienda::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-evento-salida-invalido',
        'estadia_uuid_cliente' => 'uuid-estadia-fecha-invalida',
        'salida' => '2026-09-01T08:00:00-04:00',
    ]));

    expect($resultado->estado)->toBe('rechazado')
        ->and($resultado->motivo)->not->toBeNull();

    $estadia = EstadiaHacienda::query()->where('uuid_cliente', 'uuid-estadia-fecha-invalida')->first();
    expect($estadia->salida)->toBeNull();
});
