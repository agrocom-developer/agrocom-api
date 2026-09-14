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
use App\Dominios\Operaciones\Dominio\Excepciones\LoteNoPerteneceAOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoVigenteParaAsignacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/*
 * `AsignarEquiposOrden` (HU-70, tarea 85; ampliada a N lotes por HU-92, tarea
 * 107): reparte los lotes de una orden vigente entre equipos de trabajo,
 * creando un `Trabajo` por PAR equipo↔lote — ANTES de que el piloto toque el
 * dispositivo (ver docblock de `MaquinaEstadosTrabajo::abrirPorAsignacion()`).
 */

uses(RefreshDatabase::class);

/**
 * @param  list<string>  $hectareasPorLote  una entrada por lote — se usa como
 *                                          `com_lotes.hectareas` Y como
 *                                          `ope_orden_lotes.hectareas_solicitadas`
 *                                          de ese lote (mismo valor: ningún
 *                                          test de este archivo ejercita la
 *                                          diferencia entre ambos).
 * @return array{0: OrdenAplicacion, 1: list<int>} la orden y los ids de sus lotes, en el mismo orden.
 */
function crearOrdenConLotesParaAsignacion(array $hectareasPorLote, EstadoOrdenAplicacion $estado = EstadoOrdenAplicacion::Vigente): array
{
    $cliente = Cliente::create(['razon_social' => 'Cliente asignación '.Str::random(6), 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo asignación '.Str::random(6)]);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => array_sum(array_map('floatval', $hectareasPorLote)),
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '400.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);

    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => $estado,
    ]);

    $loteIds = [];

    foreach ($hectareasPorLote as $hectareas) {
        $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-ASG-'.Str::random(6), 'hectareas' => $hectareas]);
        $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => $hectareas]);
        $loteIds[] = $lote->id;
    }

    return [$orden->refresh(), $loteIds];
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

it('acepta repartir los lotes de la orden entre dos equipos vigentes y crea un trabajo por par equipo-lote', function () {
    [$orden, $loteIds] = crearOrdenConLotesParaAsignacion(['300.00', '200.00']);
    [$loteUno, $loteDos] = $loteIds;
    $equipoUno = equipoVigenteParaAsignacion();
    $equipoDos = equipoVigenteParaAsignacion();

    $trabajos = app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipoUno->id, 'lotes' => [['lote_id' => $loteUno, 'hectareas' => '300.00']]],
        ['equipo_trabajo_id' => $equipoDos->id, 'lotes' => [['lote_id' => $loteDos, 'hectareas' => '200.00']]],
    ]);

    expect($trabajos)->toHaveCount(2)
        ->and(Trabajo::query()->count())->toBe(2);

    expect($trabajos[0]->equipo_trabajo_id)->toBe($equipoUno->id)
        ->and((string) $trabajos[0]->hectareas_declaradas)->toBe('300.00')
        ->and($trabajos[0]->orden_id)->toBe($orden->id)
        ->and($trabajos[0]->lote_id)->toBe($loteUno)
        ->and($trabajos[0]->nro_aplicacion)->toBe($orden->nro_aplicacion)
        ->and($trabajos[0]->estado)->toBe(EstadoTrabajo::Abierto)
        ->and($trabajos[0]->uuid_cliente)->not->toBeEmpty();

    expect($trabajos[1]->equipo_trabajo_id)->toBe($equipoDos->id)
        ->and($trabajos[1]->lote_id)->toBe($loteDos)
        ->and((string) $trabajos[1]->hectareas_declaradas)->toBe('200.00');

    expect($trabajos[0]->uuid_cliente)->not->toBe($trabajos[1]->uuid_cliente);
});

it('asignar un equipo a 2 lotes de la misma orden genera 2 trabajos, uno por lote', function () {
    [$orden, $loteIds] = crearOrdenConLotesParaAsignacion(['150.00', '250.00']);
    [$loteUno, $loteDos] = $loteIds;
    $equipo = equipoVigenteParaAsignacion();

    $trabajos = app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipo->id, 'lotes' => [
            ['lote_id' => $loteUno, 'hectareas' => '150.00'],
            ['lote_id' => $loteDos, 'hectareas' => '250.00'],
        ]],
    ]);

    expect($trabajos)->toHaveCount(2)
        ->and(Trabajo::query()->where('equipo_trabajo_id', $equipo->id)->count())->toBe(2)
        ->and(collect($trabajos)->pluck('lote_id')->all())->toBe([$loteUno, $loteDos])
        ->and((string) $trabajos[0]->hectareas_declaradas)->toBe('150.00')
        ->and((string) $trabajos[1]->hectareas_declaradas)->toBe('250.00');
});

it('rechaza una asignación que llevaría la suma de un lote por encima de lo solicitado, sin crear nada', function () {
    [$orden, $loteIds] = crearOrdenConLotesParaAsignacion(['500.00']);
    [$lote] = $loteIds;
    $equipoUno = equipoVigenteParaAsignacion();
    $equipoDos = equipoVigenteParaAsignacion();
    $equipoTres = equipoVigenteParaAsignacion();

    app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipoUno->id, 'lotes' => [['lote_id' => $lote, 'hectareas' => '300.00']]],
        ['equipo_trabajo_id' => $equipoDos->id, 'lotes' => [['lote_id' => $lote, 'hectareas' => '200.00']]],
    ]);

    expect(fn () => app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipoTres->id, 'lotes' => [['lote_id' => $lote, 'hectareas' => '0.01']]],
    ]))->toThrow(HectareasAsignadasSuperanLote::class);

    expect(Trabajo::query()->count())->toBe(2);
});

it('rechaza de una sola vez un reparto que en conjunto supera lo solicitado de un lote, sin crear ninguno', function () {
    [$orden, $loteIds] = crearOrdenConLotesParaAsignacion(['500.00']);
    [$lote] = $loteIds;
    $equipoUno = equipoVigenteParaAsignacion();
    $equipoDos = equipoVigenteParaAsignacion();

    expect(fn () => app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipoUno->id, 'lotes' => [['lote_id' => $lote, 'hectareas' => '300.00']]],
        ['equipo_trabajo_id' => $equipoDos->id, 'lotes' => [['lote_id' => $lote, 'hectareas' => '999.00']]],
    ]))->toThrow(HectareasAsignadasSuperanLote::class);

    expect(Trabajo::query()->count())->toBe(0);
});

it('rechaza un equipo de trabajo que no está vigente hoy, sin crear nada', function () {
    [$orden, $loteIds] = crearOrdenConLotesParaAsignacion(['500.00']);
    [$lote] = $loteIds;
    $equipoVencido = equipoVigenteParaAsignacion(desde: now()->subYear()->toDateString(), hasta: now()->subDay()->toDateString());

    expect(fn () => app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipoVencido->id, 'lotes' => [['lote_id' => $lote, 'hectareas' => '100.00']]],
    ]))->toThrow(EquipoTrabajoNoVigente::class);

    expect(Trabajo::query()->count())->toBe(0);
});

it('rechaza asignar equipos a una orden que no está vigente, sin crear nada', function () {
    [$orden, $loteIds] = crearOrdenConLotesParaAsignacion(['500.00'], EstadoOrdenAplicacion::Emitida);
    [$lote] = $loteIds;
    $equipo = equipoVigenteParaAsignacion();

    expect(fn () => app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipo->id, 'lotes' => [['lote_id' => $lote, 'hectareas' => '100.00']]],
    ]))->toThrow(OrdenNoVigenteParaAsignacion::class);

    expect(Trabajo::query()->count())->toBe(0);
});

it('rechaza un lote que no pertenece a la orden, sin crear nada', function () {
    [$orden] = crearOrdenConLotesParaAsignacion(['500.00']);
    [, $loteIdsAjenos] = crearOrdenConLotesParaAsignacion(['100.00']);
    $equipo = equipoVigenteParaAsignacion();

    expect(fn () => app(AsignarEquiposOrden::class)->ejecutar($orden, [
        ['equipo_trabajo_id' => $equipo->id, 'lotes' => [['lote_id' => $loteIdsAjenos[0], 'hectareas' => '50.00']]],
    ]))->toThrow(LoteNoPerteneceAOrden::class);

    expect(Trabajo::query()->count())->toBe(0);
});
