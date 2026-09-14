<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Aplicacion\RechazarSesion;
use App\Dominios\Operaciones\Contratos\LecturaContadoresPanel;
use App\Dominios\Operaciones\Dominio\CausaPausa;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Pausa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/*
 * Contrato de lectura `Operaciones\Contratos\LecturaContadoresPanel` (TE-14,
 * tarea 60): los tres contadores reales que este módulo aporta a los badges
 * del menú lateral. Test "unitario" en el sentido de probar la clase directo
 * (sin HTTP) — vive en Feature porque tests/Unit de este repo es PHPUnit
 * puro, sin Eloquent (mismo criterio que `LecturaReporteTecnicoEloquentTest`).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Carbon::setTestNow('2026-09-15 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function ordenParaContadoresPanel(string $sufijo, EstadoOrdenAplicacion $estado): OrdenAplicacion
{
    $cliente = Cliente::create(['razon_social' => "Cliente contadores panel {$sufijo}", 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => "Campo contadores panel {$sufijo}"]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => "L-CTP-{$sufijo}", 'hectareas' => '20.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '20.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '200.00',
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
    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => '20.00']);

    return $orden;
}

function sesionParaContadoresPanel(string $sufijo, EstadoSesion $estado): Sesion
{
    $orden = ordenParaContadoresPanel($sufijo, EstadoOrdenAplicacion::Vigente);
    $trabajo = Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-ctp-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => (int) $orden->ordenLotes()->value('lote_id'),
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T08:00:00-04:00',
    ]);
    $piloto = PerPersona::create(['nombre' => "Piloto ctp {$sufijo}", 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    return Sesion::create([
        'uuid_cliente' => "uuid-sesion-ctp-{$sufijo}",
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '10.00',
        'estado' => $estado,
        'inicio' => '2026-09-01T08:05:00-04:00',
        'fin' => $estado === EstadoSesion::Abierto ? null : '2026-09-01T09:05:00-04:00',
        'motivo_cierre' => $estado === EstadoSesion::Abierto ? null : 'completado',
        'cierre_uuid_cliente' => $estado === EstadoSesion::Abierto ? null : "uuid-cierre-ctp-{$sufijo}",
    ]);
}

it('ordenesVigentes cuenta solo las órdenes en estado vigente', function () {
    ordenParaContadoresPanel('vigente-1', EstadoOrdenAplicacion::Vigente);
    ordenParaContadoresPanel('vigente-2', EstadoOrdenAplicacion::Vigente);
    ordenParaContadoresPanel('consumida', EstadoOrdenAplicacion::Consumida);
    ordenParaContadoresPanel('emitida', EstadoOrdenAplicacion::Emitida);

    expect(app(LecturaContadoresPanel::class)->ordenesVigentes())->toBe(2);
});

it('sesionesPendientesValidacion cuenta cerrado sin anular, no abierta ni validada ni rechazada', function () {
    sesionParaContadoresPanel('cerrada', EstadoSesion::Cerrado);
    sesionParaContadoresPanel('abierta', EstadoSesion::Abierto);
    sesionParaContadoresPanel('validada', EstadoSesion::Validado);

    $rechazada = sesionParaContadoresPanel('rechazada', EstadoSesion::Cerrado);
    $jefe = PerPersona::create(['nombre' => 'Jefe ctp rechazo', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);
    (new RechazarSesion)->ejecutar($rechazada, 'motivo de prueba', $jefe->id);

    expect(app(LecturaContadoresPanel::class)->sesionesPendientesValidacion())->toBe(1);
});

it('pausasDelMes agrega cantidad y minutos solo del mes calendario en curso', function () {
    $sesion = sesionParaContadoresPanel('con-pausas', EstadoSesion::Abierto);

    Pausa::create([
        'sesion_id' => $sesion->id,
        'causa' => CausaPausa::FallaEquipo,
        'inicio' => '2026-09-05T09:00:00-04:00',
        'fin' => '2026-09-05T09:20:00-04:00',
        'duracion_minutos' => 20,
    ]);
    Pausa::create([
        'sesion_id' => $sesion->id,
        'causa' => CausaPausa::Clima,
        'inicio' => '2026-09-10T09:00:00-04:00',
        'fin' => '2026-09-10T09:40:00-04:00',
        'duracion_minutos' => 40,
    ]);
    // Fuera del mes en curso (agosto): no debe contarse.
    Pausa::create([
        'sesion_id' => $sesion->id,
        'causa' => CausaPausa::Logistica,
        'inicio' => '2026-08-20T09:00:00-04:00',
        'fin' => '2026-08-20T10:00:00-04:00',
        'duracion_minutos' => 60,
    ]);

    expect(app(LecturaContadoresPanel::class)->pausasDelMes())->toBe(['cantidad' => 2, 'totalMinutos' => 60]);
});

it('sin datos, los tres contadores devuelven cero', function () {
    $contador = app(LecturaContadoresPanel::class);

    expect($contador->ordenesVigentes())->toBe(0)
        ->and($contador->sesionesPendientesValidacion())->toBe(0)
        ->and($contador->pausasDelMes())->toBe(['cantidad' => 0, 'totalMinutos' => 0]);
});
