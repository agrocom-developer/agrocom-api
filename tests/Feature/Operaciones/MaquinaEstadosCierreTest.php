<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionSesionNoPermitida;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionTrabajoNoPermitida;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * `MaquinaEstadosTrabajo::cerrar()`/`MaquinaEstadosSesion::cerrar()` (HU-05,
 * tarea 13) probadas directamente sobre la máquina de estados, sin pasar por
 * el motor de sync — eso lo cubre tests/Feature/Api/CierreSincronizacionTest.php.
 * Cubre el criterio de aceptación 1 y 3: cerrar un `abierto` lo deja
 * `cerrado` con `fin`/`motivo_cierre` persistidos, y cerrar algo que ya está
 * `cerrado` (o abrir algo `cerrado`) se rechaza por la máquina de estados —
 * nunca un `estado = ...` suelto (invariante 7).
 */

uses(RefreshDatabase::class);

function trabajoAbiertoParaCierre(): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de prueba']);
    $campo = Campo::create(['cliente_id' => $cliente->id, 'nombre' => 'Campo de prueba']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-CIERRE', 'hectareas' => '50.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '50.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '500.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);

    return Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-cierre-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);
}

function sesionAbiertaParaCierre(Trabajo $trabajo): Sesion
{
    $piloto = PerPersona::create(['nombre' => 'Piloto de cierre', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    return Sesion::create([
        'uuid_cliente' => 'uuid-sesion-cierre-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'estado' => EstadoSesion::Abierto,
        'inicio' => '2026-09-01T10:05:00-04:00',
    ]);
}

test('MaquinaEstadosTrabajo::cerrar() deja el trabajo cerrado, con fin y cierre_uuid_cliente persistidos', function () {
    $trabajo = trabajoAbiertoParaCierre();

    $cerrado = (new MaquinaEstadosTrabajo)->cerrar($trabajo, 'uuid-cierre-evento-1', '2026-09-01T12:00:00-04:00');

    expect($cerrado->estado)->toBe(EstadoTrabajo::Cerrado)
        ->and($cerrado->cierre_uuid_cliente)->toBe('uuid-cierre-evento-1')
        ->and($cerrado->fin?->toIso8601String())->toContain('2026-09-01T12:00:00');

    expect(Trabajo::query()->findOrFail($trabajo->id)->estado)->toBe(EstadoTrabajo::Cerrado);
});

test('MaquinaEstadosTrabajo::cerrar() rechaza un trabajo que ya está cerrado', function () {
    $trabajo = trabajoAbiertoParaCierre();
    (new MaquinaEstadosTrabajo)->cerrar($trabajo, 'uuid-cierre-evento-2', '2026-09-01T12:00:00-04:00');

    expect(fn () => (new MaquinaEstadosTrabajo)->cerrar($trabajo, 'uuid-cierre-evento-3', '2026-09-01T13:00:00-04:00'))
        ->toThrow(TransicionTrabajoNoPermitida::class);
});

test('MaquinaEstadosSesion::cerrar() deja la sesión cerrada, con fin y motivo_cierre persistidos', function () {
    $trabajo = trabajoAbiertoParaCierre();
    $sesion = sesionAbiertaParaCierre($trabajo);

    $cerrada = (new MaquinaEstadosSesion)->cerrar($sesion, 'uuid-cierre-sesion-1', '2026-09-01T12:00:00-04:00', 'completado');

    expect($cerrada->estado)->toBe(EstadoSesion::Cerrado)
        ->and($cerrada->cierre_uuid_cliente)->toBe('uuid-cierre-sesion-1')
        ->and($cerrada->motivo_cierre)->toBe('completado')
        ->and($cerrada->fin?->toIso8601String())->toContain('2026-09-01T12:00:00');

    expect(Sesion::query()->findOrFail($sesion->id)->estado)->toBe(EstadoSesion::Cerrado);
});

test('MaquinaEstadosSesion::cerrar() rechaza una sesión que ya está cerrada', function () {
    $trabajo = trabajoAbiertoParaCierre();
    $sesion = sesionAbiertaParaCierre($trabajo);
    (new MaquinaEstadosSesion)->cerrar($sesion, 'uuid-cierre-sesion-2', '2026-09-01T12:00:00-04:00', 'completado');

    expect(fn () => (new MaquinaEstadosSesion)->cerrar($sesion, 'uuid-cierre-sesion-3', '2026-09-01T13:00:00-04:00', 'otro'))
        ->toThrow(TransicionSesionNoPermitida::class);
});
