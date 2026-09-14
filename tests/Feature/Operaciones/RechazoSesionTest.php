<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Aplicacion\RechazarSesion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\PilotoNoPuedeDecidirSuPropiaSesion;
use App\Dominios\Operaciones\Dominio\Excepciones\SesionNoDisponibleParaDecision;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\SesionRechazo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * `Aplicacion/RechazarSesion` (HU-14, tarea 14): el mecanismo de corrección
 * de la invariante 2 de CLAUDE.md — "las correcciones son registros nuevos
 * con anula_a_id, motivo y autor, nunca un UPDATE sobre lo validado".
 * Diseño completo en runs/14.md.
 *
 * Cubre el criterio de aceptación 3 (assert explícito de que la fila
 * original no cambia sus columnas de negocio) y, junto con
 * tests/Feature/Operaciones/ValidarSesionTest.php, la policy validador≠piloto
 * (invariante 4) también para el rechazo.
 */

uses(RefreshDatabase::class);

function trabajoAbiertoParaRechazo(): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de rechazo', 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo de rechazo']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-RECHAZO', 'hectareas' => '50.00']);
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
        'uuid_cliente' => 'uuid-trabajo-rechazo-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);
}

/** @return array{0: Sesion, 1: PerPersona} sesión + su piloto */
function sesionCerradaParaRechazo(?Trabajo $trabajo = null, EstadoSesion $estado = EstadoSesion::Cerrado): array
{
    $trabajo ??= trabajoAbiertoParaRechazo();
    $piloto = PerPersona::create(['nombre' => 'Piloto de rechazo', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $sesion = Sesion::create([
        'uuid_cliente' => 'uuid-sesion-rechazo-'.uniqid(),
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

    return [$sesion, $piloto];
}

test('rechazar crea la fila de corrección con anula_a_id/motivo/autor y no modifica columnas de negocio de la original', function () {
    [$sesion, $piloto] = sesionCerradaParaRechazo();
    $jefe = PerPersona::create(['nombre' => 'Jefe que rechaza', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    $originalAntes = $sesion->replicate();

    $rechazo = (new RechazarSesion)->ejecutar($sesion, 'Hectáreas declaradas no coinciden con la orden', $jefe->id);

    expect($rechazo)->toBeInstanceOf(SesionRechazo::class)
        ->and($rechazo->anula_a_id)->toBe($sesion->id)
        ->and($rechazo->motivo)->toBe('Hectáreas declaradas no coinciden con la orden')
        ->and($rechazo->rechazado_por)->toBe($jefe->id);

    // Assert explícito (criterio de aceptación 3): la fila ORIGINAL conserva
    // sus columnas de negocio intactas, `estado` incluido — nunca pasa a
    // "rechazado", se queda `cerrado` para siempre.
    $recargada = Sesion::query()->findOrFail($sesion->id);
    expect($recargada->estado)->toBe(EstadoSesion::Cerrado)
        ->and($recargada->piloto_id)->toBe($originalAntes->piloto_id)
        ->and($recargada->hectareas_declaradas)->toBe($originalAntes->hectareas_declaradas)
        ->and($recargada->motivo_cierre)->toBe($originalAntes->motivo_cierre)
        ->and($recargada->fin?->toIso8601String())->toBe($originalAntes->fin?->toIso8601String())
        ->and($recargada->cierre_uuid_cliente)->toBe($originalAntes->cierre_uuid_cliente)
        // La única huella del rechazo sobre la fila original: la marca,
        // nunca el estado.
        ->and($recargada->anulada_en)->not->toBeNull();

    expect(SesionRechazo::query()->where('anula_a_id', $sesion->id)->count())->toBe(1);
});

test('rechazar una sesión que todavía está abierta se rechaza por estado inválido', function () {
    [$sesion] = sesionCerradaParaRechazo(estado: EstadoSesion::Abierto);
    $jefe = PerPersona::create(['nombre' => 'Jefe que rechaza', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    expect(fn () => (new RechazarSesion)->ejecutar($sesion, 'motivo cualquiera', $jefe->id))
        ->toThrow(SesionNoDisponibleParaDecision::class);

    expect(SesionRechazo::query()->count())->toBe(0);
});

test('rechazar una sesión ya rechazada antes se rechaza — no se acumulan correcciones', function () {
    [$sesion] = sesionCerradaParaRechazo();
    $jefe = PerPersona::create(['nombre' => 'Jefe que rechaza', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    (new RechazarSesion)->ejecutar($sesion, 'primer motivo', $jefe->id);

    expect(fn () => (new RechazarSesion)->ejecutar($sesion, 'segundo intento', $jefe->id))
        ->toThrow(SesionNoDisponibleParaDecision::class);

    expect(SesionRechazo::query()->where('anula_a_id', $sesion->id)->count())->toBe(1);
});

test('el piloto de la sesión no puede rechazar su propia sesión (invariante 4)', function () {
    [$sesion, $piloto] = sesionCerradaParaRechazo();

    expect(fn () => (new RechazarSesion)->ejecutar($sesion, 'motivo cualquiera', $piloto->id))
        ->toThrow(PilotoNoPuedeDecidirSuPropiaSesion::class);

    expect(Sesion::query()->findOrFail($sesion->id)->anulada_en)->toBeNull();
});

test('un jefe que no es el piloto de la sesión sí puede rechazarla', function () {
    [$sesion] = sesionCerradaParaRechazo();
    $jefeAjeno = PerPersona::create(['nombre' => 'Jefe ajeno', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    $rechazo = (new RechazarSesion)->ejecutar($sesion, 'motivo cualquiera', $jefeAjeno->id);

    expect($rechazo->rechazado_por)->toBe($jefeAjeno->id);
});
