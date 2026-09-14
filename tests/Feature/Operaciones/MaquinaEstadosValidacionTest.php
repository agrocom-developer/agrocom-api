<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Contratos\Eventos\SesionValidada;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionSesionNoPermitida;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

/*
 * `MaquinaEstadosSesion::validar()` (HU-14, tarea 14) probada directamente
 * sobre la máquina de estados, sin pasar por el panel — eso lo cubre
 * tests/Feature/Operaciones/ValidacionSesionesPanelTest.php. Cubre el
 * criterio de aceptación 1: `cerrado → validado`, disparando `SesionValidada`
 * UNA sola vez aunque se reintente.
 *
 * La policy validador≠piloto (invariante 4) NO se prueba acá: esta clase es
 * solo la que escribe `estado` (invariante 7), no la que autoriza — eso vive
 * en `Aplicacion/ValidarSesion` y se prueba en
 * tests/Feature/Operaciones/ValidarSesionTest.php.
 */

uses(RefreshDatabase::class);

function trabajoAbiertoParaValidacion(): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de validación', 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo de validación']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-VALIDA', 'hectareas' => '50.00']);
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
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => '50.00']);

    return Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-validacion-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);
}

function sesionParaValidacion(Trabajo $trabajo, EstadoSesion $estado = EstadoSesion::Cerrado): Sesion
{
    $piloto = PerPersona::create(['nombre' => 'Piloto de validación', 'rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => '150.00', 'activo' => true]);

    return Sesion::create([
        'uuid_cliente' => 'uuid-sesion-validacion-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '12.00',
        'estado' => $estado,
        'inicio' => '2026-09-01T10:05:00-04:00',
        'fin' => $estado === EstadoSesion::Abierto ? null : '2026-09-01T12:00:00-04:00',
        'motivo_cierre' => $estado === EstadoSesion::Abierto ? null : 'completado',
        'cierre_uuid_cliente' => $estado === EstadoSesion::Abierto ? null : 'uuid-cierre-'.uniqid(),
    ]);
}

test('validar() aprueba una sesión cerrada, persiste validado_por/fecha_validacion y dispara SesionValidada', function () {
    Event::fake([SesionValidada::class]);

    $trabajo = trabajoAbiertoParaValidacion();
    $sesion = sesionParaValidacion($trabajo);
    $jefe = PerPersona::create(['nombre' => 'Jefe validador', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    $validada = (new MaquinaEstadosSesion)->validar($sesion, $jefe->id);

    expect($validada->estado)->toBe(EstadoSesion::Validado)
        ->and($validada->validado_por)->toBe($jefe->id)
        ->and($validada->fecha_validacion)->not->toBeNull();

    $recargada = Sesion::query()->findOrFail($sesion->id);
    expect($recargada->estado)->toBe(EstadoSesion::Validado)
        ->and($recargada->validado_por)->toBe($jefe->id);

    Event::assertDispatched(SesionValidada::class, fn (SesionValidada $evento): bool => $evento->sesionId === $sesion->id);
    Event::assertDispatchedTimes(SesionValidada::class, 1);
});

test('validar() es idempotente: un reintento sobre una sesión ya validada no vuelve a disparar el evento', function () {
    Event::fake([SesionValidada::class]);

    $trabajo = trabajoAbiertoParaValidacion();
    $sesion = sesionParaValidacion($trabajo);
    $jefe = PerPersona::create(['nombre' => 'Jefe validador', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    $maquina = new MaquinaEstadosSesion;
    $maquina->validar($sesion, $jefe->id);
    $segundaVez = $maquina->validar($sesion, $jefe->id);

    expect($segundaVez->estado)->toBe(EstadoSesion::Validado);
    Event::assertDispatchedTimes(SesionValidada::class, 1);
});

test('validar() rechaza una sesión que todavía está abierta', function () {
    $trabajo = trabajoAbiertoParaValidacion();
    $sesion = sesionParaValidacion($trabajo, EstadoSesion::Abierto);
    $jefe = PerPersona::create(['nombre' => 'Jefe validador', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    expect(fn () => (new MaquinaEstadosSesion)->validar($sesion, $jefe->id))
        ->toThrow(TransicionSesionNoPermitida::class);

    expect(Sesion::query()->findOrFail($sesion->id)->estado)->toBe(EstadoSesion::Abierto);
});
