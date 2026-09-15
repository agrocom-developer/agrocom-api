<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Aplicacion\AgregarPausasPorCausa;
use App\Dominios\Operaciones\Aplicacion\RegistrarPausa;
use App\Dominios\Operaciones\Dominio\CausaPausa;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\PausaFinAnteriorAInicio;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Pausa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * `Aplicacion/RegistrarPausa` y `Aplicacion/AgregarPausasPorCausa` (HU-44,
 * tarea 58; DS-01): guarda de dominio (`fin > inicio`) y exactitud del
 * agregado por causa — a nivel de caso de uso, sin pasar por HTTP (esa parte
 * está en `tests/Feature/Operaciones/PausasPanelTest.php`).
 */

uses(RefreshDatabase::class);

function sesionParaPausas(): Sesion
{
    $cliente = Cliente::create(['razon_social' => 'Cliente pausas '.uniqid(), 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $lote = Lote::create(['propiedad_id' => $propiedad->id, 'codigo' => 'L-PAU-'.uniqid(), 'hectareas' => '50.00']);
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
    $trabajo = Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-pausas-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T08:00:00-04:00',
    ]);
    $piloto = PerPersona::create(['nombre' => 'Piloto pausas '.uniqid(), 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    return Sesion::create([
        'uuid_cliente' => 'uuid-sesion-pausas-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '10.00',
        'estado' => EstadoSesion::Abierto,
        'inicio' => '2026-09-01T08:05:00-04:00',
    ]);
}

it('registra una pausa válida calculando la duración en minutos', function () {
    $sesion = sesionParaPausas();

    $pausa = (new RegistrarPausa)->ejecutar(
        $sesion->id,
        CausaPausa::FallaEquipo,
        '2026-09-01T09:00:00-04:00',
        '2026-09-01T09:35:00-04:00',
    );

    expect($pausa->sesion_id)->toBe($sesion->id)
        ->and($pausa->causa)->toBe(CausaPausa::FallaEquipo)
        ->and($pausa->duracion_minutos)->toBe(35);
});

it('rechaza una pausa cuyo fin es anterior o igual a su inicio', function () {
    $sesion = sesionParaPausas();

    (new RegistrarPausa)->ejecutar(
        $sesion->id,
        CausaPausa::Clima,
        '2026-09-01T09:00:00-04:00',
        '2026-09-01T08:59:00-04:00',
    );
})->throws(PausaFinAnteriorAInicio::class);

it('rechaza una pausa cuyo fin es exactamente igual al inicio', function () {
    $sesion = sesionParaPausas();

    (new RegistrarPausa)->ejecutar(
        $sesion->id,
        CausaPausa::Clima,
        '2026-09-01T09:00:00-04:00',
        '2026-09-01T09:00:00-04:00',
    );
})->throws(PausaFinAnteriorAInicio::class);

it('agrega los minutos exactos por causa dentro del período filtrado, ignorando pausas fuera de él', function () {
    $sesion = sesionParaPausas();

    Pausa::create(['sesion_id' => $sesion->id, 'causa' => CausaPausa::Clima, 'inicio' => '2026-09-05T08:00:00Z', 'fin' => '2026-09-05T08:40:00Z', 'duracion_minutos' => 40]);
    Pausa::create(['sesion_id' => $sesion->id, 'causa' => CausaPausa::Clima, 'inicio' => '2026-09-10T08:00:00Z', 'fin' => '2026-09-10T08:20:00Z', 'duracion_minutos' => 20]);
    Pausa::create(['sesion_id' => $sesion->id, 'causa' => CausaPausa::FallaEquipo, 'inicio' => '2026-09-12T08:00:00Z', 'fin' => '2026-09-12T09:10:00Z', 'duracion_minutos' => 70]);
    // Fuera del período filtrado (agosto): no debe sumar.
    Pausa::create(['sesion_id' => $sesion->id, 'causa' => CausaPausa::Clima, 'inicio' => '2026-08-01T08:00:00Z', 'fin' => '2026-08-01T09:00:00Z', 'duracion_minutos' => 60]);

    $agregado = (new AgregarPausasPorCausa)->ejecutar('2026-09');

    expect($agregado['por_causa'][CausaPausa::Clima->value])->toBe(60)
        ->and($agregado['por_causa'][CausaPausa::FallaEquipo->value])->toBe(70)
        ->and($agregado['por_causa'][CausaPausa::Logistica->value])->toBe(0)
        ->and($agregado['total_minutos'])->toBe(130);
});
