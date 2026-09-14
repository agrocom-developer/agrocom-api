<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Aplicacion\AsignarEquiposOrden;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\EquipoTrabajoNoVigente;
use App\Dominios\Operaciones\Dominio\Excepciones\HectareasAsignadasSuperanLote;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoVigenteParaAsignacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/*
 * `AsignarEquiposOrden` (HU-70, tarea 85): reparte las hectáreas del lote de
 * una orden vigente entre equipos de trabajo, creando un `Trabajo` por
 * equipo — ANTES de que el piloto toque el dispositivo (ver docblock de
 * `MaquinaEstadosTrabajo::abrirPorAsignacion()`).
 */

uses(RefreshDatabase::class);

function ordenVigenteParaAsignacion(string $hectareasLote, EstadoOrdenAplicacion $estado = EstadoOrdenAplicacion::Vigente): OrdenAplicacion
{
    $cliente = Cliente::create(['razon_social' => 'Cliente asignación '.Str::random(6), 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo asignación '.Str::random(6)]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-ASG-'.Str::random(6), 'hectareas' => $hectareasLote]);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => $hectareasLote,
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '400.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);

    return OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => $estado,
    ]);
}

function equipoVigenteParaAsignacion(?string $desde = null, ?string $hasta = null): EquipoTrabajo
{
    $base = PerBase::create(['nombre' => 'Base asignación '.Str::random(6)]);

    return EquipoTrabajo::create([
        'codigo' => 'EQ-ASG-'.Str::random(6),
        'base_id' => $base->id,
        'estado' => EstadoEquipoTrabajo::Activo,
        'desde' => $desde ?? now()->subYear()->toDateString(),
        'hasta' => $hasta,
    ]);
}

it('acepta repartir las hectáreas del lote entre dos equipos vigentes y crea un trabajo por equipo', function () {
    $orden = ordenVigenteParaAsignacion('500.00');
    $equipoUno = equipoVigenteParaAsignacion();
    $equipoDos = equipoVigenteParaAsignacion();

    $trabajos = app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipoUno->id, 'hectareas' => '300.00'],
        ['equipo_trabajo_id' => $equipoDos->id, 'hectareas' => '200.00'],
    ]);

    expect($trabajos)->toHaveCount(2)
        ->and(Trabajo::query()->count())->toBe(2);

    expect($trabajos[0]->equipo_trabajo_id)->toBe($equipoUno->id)
        ->and((string) $trabajos[0]->hectareas_declaradas)->toBe('300.00')
        ->and($trabajos[0]->orden_id)->toBe($orden->id)
        ->and($trabajos[0]->lote_id)->toBe($orden->lote_id)
        ->and($trabajos[0]->nro_aplicacion)->toBe($orden->nro_aplicacion)
        ->and($trabajos[0]->estado)->toBe(EstadoTrabajo::Abierto)
        ->and($trabajos[0]->uuid_cliente)->not->toBeEmpty();

    expect($trabajos[1]->equipo_trabajo_id)->toBe($equipoDos->id)
        ->and((string) $trabajos[1]->hectareas_declaradas)->toBe('200.00');

    expect($trabajos[0]->uuid_cliente)->not->toBe($trabajos[1]->uuid_cliente);
});

it('rechaza una asignación que llevaría la suma por encima de las hectáreas del lote, sin crear nada', function () {
    $orden = ordenVigenteParaAsignacion('500.00');
    $equipoUno = equipoVigenteParaAsignacion();
    $equipoDos = equipoVigenteParaAsignacion();
    $equipoTres = equipoVigenteParaAsignacion();

    app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipoUno->id, 'hectareas' => '300.00'],
        ['equipo_trabajo_id' => $equipoDos->id, 'hectareas' => '200.00'],
    ]);

    expect(fn () => app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipoTres->id, 'hectareas' => '0.01'],
    ]))->toThrow(HectareasAsignadasSuperanLote::class);

    expect(Trabajo::query()->count())->toBe(2);
});

it('rechaza de una sola vez un lote de asignaciones que en conjunto superan el lote, sin crear ninguna', function () {
    $orden = ordenVigenteParaAsignacion('500.00');
    $equipoUno = equipoVigenteParaAsignacion();
    $equipoDos = equipoVigenteParaAsignacion();

    expect(fn () => app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipoUno->id, 'hectareas' => '300.00'],
        ['equipo_trabajo_id' => $equipoDos->id, 'hectareas' => '999.00'],
    ]))->toThrow(HectareasAsignadasSuperanLote::class);

    expect(Trabajo::query()->count())->toBe(0);
});

it('rechaza un equipo de trabajo que no está vigente hoy, sin crear nada', function () {
    $orden = ordenVigenteParaAsignacion('500.00');
    $equipoVencido = equipoVigenteParaAsignacion(desde: now()->subYear()->toDateString(), hasta: now()->subDay()->toDateString());

    expect(fn () => app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipoVencido->id, 'hectareas' => '100.00'],
    ]))->toThrow(EquipoTrabajoNoVigente::class);

    expect(Trabajo::query()->count())->toBe(0);
});

it('rechaza asignar equipos a una orden que no está vigente, sin crear nada', function () {
    $orden = ordenVigenteParaAsignacion('500.00', EstadoOrdenAplicacion::Emitida);
    $equipo = equipoVigenteParaAsignacion();

    expect(fn () => app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipo->id, 'hectareas' => '100.00'],
    ]))->toThrow(OrdenNoVigenteParaAsignacion::class);

    expect(Trabajo::query()->count())->toBe(0);
});
