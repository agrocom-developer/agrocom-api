<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * `Aplicacion/MaquinaEstados/MaquinaEstadosTrabajo` y `...Sesion` (invariante
 * 7 de CLAUDE.md, tarea 09): la única forma de crear `trabajo`/`sesion` con su
 * estado inicial. Las transiciones puras ya se prueban sin DB en
 * `tests/Unit/MaquinaEstadosOperacionesTest.php`; acá se ejercita la
 * persistencia real, incluido el `UNIQUE` parcial por `uuid_cliente`.
 */

uses(RefreshDatabase::class);

function ordenVigenteDePrueba(): OrdenAplicacion
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de prueba', 'tipo_persona' => 'juridica']);

    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo de prueba']);
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

test('abrir un trabajo lo crea en estado abierto', function () {
    $orden = ordenVigenteDePrueba();

    $trabajo = (new MaquinaEstadosTrabajo)->abrir([
        'uuid_cliente' => 'uuid-trabajo-1',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => now(),
    ]);

    expect($trabajo->estado)->toBe(EstadoTrabajo::Abierto)
        ->and($trabajo->wasRecentlyCreated)->toBeTrue();

    expect(Trabajo::query()->where('uuid_cliente', 'uuid-trabajo-1')->exists())->toBeTrue();
});

test('reabrir el mismo uuid_cliente de un trabajo choca con el índice único, no con un chequeo previo', function () {
    $orden = ordenVigenteDePrueba();

    (new MaquinaEstadosTrabajo)->abrir([
        'uuid_cliente' => 'uuid-trabajo-repetido',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => now(),
    ]);

    (new MaquinaEstadosTrabajo)->abrir([
        'uuid_cliente' => 'uuid-trabajo-repetido',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => now(),
    ]);
})->throws(QueryException::class);

test('abrir una sesión la crea en estado abierto', function () {
    $orden = ordenVigenteDePrueba();

    $trabajo = (new MaquinaEstadosTrabajo)->abrir([
        'uuid_cliente' => 'uuid-trabajo-para-sesion',
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'inicio' => now(),
    ]);

    $piloto = PerPersona::create(['nombre' => 'Piloto de prueba', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $sesion = (new MaquinaEstadosSesion)->abrir([
        'uuid_cliente' => 'uuid-sesion-1',
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'inicio' => now(),
    ]);

    expect($sesion->estado)->toBe(EstadoSesion::Abierto)
        ->and($sesion->wasRecentlyCreated)->toBeTrue();

    expect(Sesion::query()->where('uuid_cliente', 'uuid-sesion-1')->exists())->toBeTrue();
});
