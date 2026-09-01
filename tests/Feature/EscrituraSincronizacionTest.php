<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Contratos\AperturaSesion;
use App\Dominios\Operaciones\Contratos\AperturaTrabajo;
use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Contrato de escritura de `Operaciones` para el motor de sync (ADR 0003,
 * regla 2; TE-05, tarea 09) — probado a nivel de caso de uso, sin pasar por
 * HTTP (eso lo cubre `tests/Feature/Api/SincronizarLoteTest.php`). Acá se
 * ejercita lo que el prompt de la tarea llama "el problema difícil": la
 * resolución de `sesion → trabajo` por `uuid_cliente`, y la traducción de la
 * violación del índice único a `duplicado`.
 */

uses(RefreshDatabase::class);

function ordenVigenteParaEscritura(): OrdenAplicacion
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de prueba']);

    $campo = Campo::create(['cliente_id' => $cliente->id, 'nombre' => 'Campo de prueba']);

    $lote = Lote::create([
        'campo_id' => $campo->id,
        'codigo' => 'L-TEST',
        'hectareas' => '50.00',
    ]);

    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '50.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '500.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);

    return OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
}

function pilotoParaEscritura(): PerPersona
{
    return PerPersona::create(['nombre' => 'Piloto de prueba', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
}

test('abrirTrabajo con datos válidos aplica y persiste la fila', function () {
    $orden = ordenVigenteParaEscritura();
    $contrato = app(EscrituraSincronizacion::class);

    $datos = AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-1',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);

    $resultado = $contrato->abrirTrabajo($datos);

    expect($resultado->estado)->toBe('aplicado')
        ->and($resultado->motivo)->toBeNull();

    expect(Trabajo::query()->where('uuid_cliente', 'uuid-trabajo-1')->exists())->toBeTrue();
});

test('abrirTrabajo con el mismo uuid_cliente responde duplicado sin crear una fila nueva', function () {
    $orden = ordenVigenteParaEscritura();
    $contrato = app(EscrituraSincronizacion::class);

    $datos = AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-repetido',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);

    expect($contrato->abrirTrabajo($datos)->estado)->toBe('aplicado');

    $resultado = $contrato->abrirTrabajo($datos);

    expect($resultado->estado)->toBe('duplicado')
        ->and(Trabajo::query()->where('uuid_cliente', 'uuid-trabajo-repetido')->count())->toBe(1);
});

test('abrirSesion resuelve su trabajo por uuid_cliente, no por id de servidor', function () {
    $orden = ordenVigenteParaEscritura();
    $piloto = pilotoParaEscritura();
    $contrato = app(EscrituraSincronizacion::class);

    $trabajo = AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-para-sesion',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);
    $contrato->abrirTrabajo($trabajo);

    $sesion = AperturaSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-sesion-1',
        'trabajo_uuid_cliente' => 'uuid-trabajo-para-sesion',
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'inicio' => '2026-09-01T10:05:00-04:00',
    ]);

    $resultado = $contrato->abrirSesion($sesion);

    expect($resultado->estado)->toBe('aplicado');

    $trabajoPersistido = Trabajo::query()->where('uuid_cliente', 'uuid-trabajo-para-sesion')->firstOrFail();
    $sesionPersistida = Sesion::query()->where('uuid_cliente', 'uuid-sesion-1')->firstOrFail();

    expect($sesionPersistida->trabajo_id)->toBe($trabajoPersistido->id);
});

test('abrirSesion con un trabajo_uuid_cliente que no existe se rechaza sin romper nada', function () {
    $piloto = pilotoParaEscritura();
    $contrato = app(EscrituraSincronizacion::class);

    $sesion = AperturaSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-sesion-huerfana',
        'trabajo_uuid_cliente' => 'uuid-trabajo-inexistente',
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'inicio' => '2026-09-01T10:05:00-04:00',
    ]);

    $resultado = $contrato->abrirSesion($sesion);

    expect($resultado->estado)->toBe('rechazado')
        ->and($resultado->motivo)->not->toBeNull();

    expect(Sesion::query()->where('uuid_cliente', 'uuid-sesion-huerfana')->exists())->toBeFalse();
});

test('abrirSesion con el mismo uuid_cliente responde duplicado sin crear una fila nueva', function () {
    $orden = ordenVigenteParaEscritura();
    $piloto = pilotoParaEscritura();
    $contrato = app(EscrituraSincronizacion::class);

    $contrato->abrirTrabajo(AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-trabajo-de-sesion-duplicada',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]));

    $sesion = AperturaSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-sesion-repetida',
        'trabajo_uuid_cliente' => 'uuid-trabajo-de-sesion-duplicada',
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'inicio' => '2026-09-01T10:05:00-04:00',
    ]);

    expect($contrato->abrirSesion($sesion)->estado)->toBe('aplicado');

    $resultado = $contrato->abrirSesion($sesion);

    expect($resultado->estado)->toBe('duplicado')
        ->and(Sesion::query()->where('uuid_cliente', 'uuid-sesion-repetida')->count())->toBe(1);
});

test('AperturaTrabajo::intentarDesdeArreglo devuelve null ante un campo requerido faltante', function () {
    expect(AperturaTrabajo::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-incompleto',
        'orden_id' => 1,
        // falta lote_id, nro_aplicacion, inicio
    ]))->toBeNull();
});

test('AperturaSesion::intentarDesdeArreglo devuelve null ante un campo requerido faltante', function () {
    expect(AperturaSesion::intentarDesdeArreglo([
        'uuid_cliente' => 'uuid-incompleto',
        'trabajo_uuid_cliente' => 'uuid-trabajo',
        // falta secuencia, piloto_id, inicio
    ]))->toBeNull();
});
