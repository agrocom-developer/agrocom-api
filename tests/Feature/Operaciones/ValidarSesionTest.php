<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\RechazarSesion;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\PilotoNoPuedeDecidirSuPropiaSesion;
use App\Dominios\Operaciones\Dominio\Excepciones\SesionNoDisponibleParaDecision;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * `Aplicacion/ValidarSesion` (HU-14, tarea 14): integra la policy de la
 * invariante 4 ("validador ≠ piloto de esa sesión, a nivel de persona") con
 * `MaquinaEstadosSesion::validar()`. Cubre el criterio de aceptación 2: el
 * piloto de la sesión no puede validarla.
 */

uses(RefreshDatabase::class);

function trabajoAbiertoParaValidarSesion(): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de validación de sesión']);
    $campo = Campo::create(['cliente_id' => $cliente->id, 'nombre' => 'Campo de validación de sesión']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-VALSES', 'hectareas' => '50.00']);
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
        'uuid_cliente' => 'uuid-trabajo-valses-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);
}

/** @return array{0: Sesion, 1: PerPersona} sesión cerrada + su piloto */
function sesionCerradaParaValidarSesion(): array
{
    $trabajo = trabajoAbiertoParaValidarSesion();
    $piloto = PerPersona::create(['nombre' => 'Piloto a validar', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $sesion = Sesion::create([
        'uuid_cliente' => 'uuid-sesion-valses-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '12.00',
        'estado' => EstadoSesion::Cerrado,
        'inicio' => '2026-09-01T10:05:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => 'uuid-cierre-'.uniqid(),
    ]);

    return [$sesion, $piloto];
}

test('un jefe que no es el piloto de la sesión la valida sin problema', function () {
    [$sesion, $piloto] = sesionCerradaParaValidarSesion();
    $jefe = PerPersona::create(['nombre' => 'Jefe validador', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    $validada = (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id);

    expect($validada->estado)->toBe(EstadoSesion::Validado)
        ->and($validada->validado_por)->toBe($jefe->id);
});

test('el piloto de la sesión no puede validar su propia sesión (invariante 4)', function () {
    [$sesion, $piloto] = sesionCerradaParaValidarSesion();

    expect(fn () => (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $piloto->id))
        ->toThrow(PilotoNoPuedeDecidirSuPropiaSesion::class);

    expect(Sesion::query()->findOrFail($sesion->id)->estado)->toBe(EstadoSesion::Cerrado);
});

test('un jefe que también es piloto (misma persona, otro rol) no valida su propia sesión', function () {
    // Invariante 4, literal: "a nivel de persona — no de rol". La misma
    // persona vuela y coordina; el chequeo compara `per_personas.id`, nunca
    // el rol con el que entró al panel.
    [$sesion, $pilotoQueTambienEsJefe] = sesionCerradaParaValidarSesion();

    expect(fn () => (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $pilotoQueTambienEsJefe->id))
        ->toThrow(PilotoNoPuedeDecidirSuPropiaSesion::class);
});

test('no se puede validar una sesión que ya fue rechazada', function () {
    [$sesion] = sesionCerradaParaValidarSesion();
    $jefe = PerPersona::create(['nombre' => 'Jefe que rechaza', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    (new RechazarSesion)->ejecutar($sesion, 'motivo de rechazo', $jefe->id);

    $otroJefe = PerPersona::create(['nombre' => 'Otro jefe', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    expect(fn () => (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion->refresh(), $otroJefe->id))
        ->toThrow(SesionNoDisponibleParaDecision::class);
});
