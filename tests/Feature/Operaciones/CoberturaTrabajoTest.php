<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Aplicacion\CalcularCoberturaTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoCoberturaTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/*
 * `CalcularCoberturaTrabajo` (espec §5, HU-07, tarea 20): "parcial"/
 * "observado" como PROYECCIÓN DERIVADA, mismo criterio que
 * `Trabajo::estadoTablero()`/`cuadreCaldo()` — ver runs/20.md para el porqué
 * de esta decisión de diseño (camino 2 del prompt) en vez de reabrir
 * `EstadoTrabajo`/`TransicionesTrabajo`.
 *
 * Cada test fija su propia tolerancia con `config()` (criterio del prompt:
 * "el test usa un valor propio, explícito, no el que termine en el .env de
 * producción" — nunca el default de `config/operaciones.php`).
 */

uses(RefreshDatabase::class);

/**
 * @param  list<array<string, mixed>>  $sesiones
 */
function trabajoParaCobertura(string $hectareasLote, array $sesiones = []): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente cobertura '.Str::random(6), 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $lote = Lote::create(['propiedad_id' => $propiedad->id, 'codigo' => 'L-COB-'.Str::random(6), 'hectareas' => $hectareasLote]);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => $hectareasLote,
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
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => $hectareasLote]);

    $trabajo = Trabajo::create([
        'uuid_cliente' => (string) Str::uuid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T09:00:00-04:00',
    ]);

    foreach ($sesiones as $secuencia => $atributosSesion) {
        $piloto = PerPersona::create(['nombre' => 'Piloto '.Str::random(6), 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

        Sesion::create([
            'uuid_cliente' => (string) Str::uuid(),
            'trabajo_id' => $trabajo->id,
            'secuencia' => $secuencia + 1,
            'piloto_id' => $piloto->id,
            'estado' => EstadoSesion::Cerrado,
            'inicio' => '2026-09-01T09:05:00-04:00',
            'fin' => '2026-09-01T10:05:00-04:00',
            'motivo_cierre' => 'completado',
            'cierre_uuid_cliente' => (string) Str::uuid(),
            ...$atributosSesion,
        ]);
    }

    return $trabajo->fresh();
}

function coberturaDe(Trabajo $trabajo): ?EstadoCoberturaTrabajo
{
    return app(CalcularCoberturaTrabajo::class)->ejecutar($trabajo);
}

it('un relevo (motivo distinto de completado) con hectáreas del lote sin cubrir deja la cobertura parcial', function () {
    $trabajo = trabajoParaCobertura('20.00', [
        ['hectareas_declaradas' => '8.00', 'motivo_cierre' => 'relevo_piloto'],
    ]);

    expect(coberturaDe($trabajo))->toBe(EstadoCoberturaTrabajo::Parcial);
});

it('completar las hectáreas del lote tras un relevo deja la cobertura completa, no parcial', function () {
    $trabajo = trabajoParaCobertura('20.00', [
        ['hectareas_declaradas' => '8.00', 'motivo_cierre' => 'relevo_piloto'],
        ['hectareas_declaradas' => '12.00', 'motivo_cierre' => 'completado'],
    ]);

    expect(coberturaDe($trabajo))->toBe(EstadoCoberturaTrabajo::Completo);
});

it('un relevo con dos sesiones de dron distinto que no cubren el lote sigue parcial', function () {
    $dronUno = Dron::create(['identificador' => 'DJI-COB-01']);
    $dronDos = Dron::create(['identificador' => 'DJI-COB-02']);

    $trabajo = trabajoParaCobertura('20.00', [
        ['hectareas_declaradas' => '5.00', 'motivo_cierre' => 'cambio_dron', 'dron_id' => $dronUno->id],
        ['hectareas_declaradas' => '6.00', 'motivo_cierre' => 'relevo_piloto', 'dron_id' => $dronDos->id],
    ]);

    expect(coberturaDe($trabajo))->toBe(EstadoCoberturaTrabajo::Parcial)
        ->and($trabajo->sesiones()->pluck('dron_id')->all())->toBe([$dronUno->id, $dronDos->id]);
});

it('una sesión sin cerrar (sin motivo) y hectáreas sin cubrir no cuenta como parcial', function () {
    $trabajo = trabajoParaCobertura('20.00', [
        ['hectareas_declaradas' => '0.00', 'motivo_cierre' => null, 'estado' => EstadoSesion::Abierto, 'fin' => null, 'cierre_uuid_cliente' => null],
    ]);

    expect(coberturaDe($trabajo))->toBeNull();
});

it('dentro de la tolerancia configurada, exactamente en el límite, no dispara observado', function () {
    config(['operaciones.tolerancia_solape_hectareas' => '2.00']);

    $trabajo = trabajoParaCobertura('20.00', [
        ['hectareas_declaradas' => '22.00'],
    ]);

    expect(coberturaDe($trabajo))->toBe(EstadoCoberturaTrabajo::Completo);
});

it('superar la tolerancia configurada deja el trabajo observado', function () {
    config(['operaciones.tolerancia_solape_hectareas' => '2.00']);

    $trabajo = trabajoParaCobertura('20.00', [
        ['hectareas_declaradas' => '22.01'],
    ]);

    expect(coberturaDe($trabajo))->toBe(EstadoCoberturaTrabajo::Observado);
});

it('con tolerancia cero, cualquier exceso sobre el lote deja el trabajo observado', function () {
    config(['operaciones.tolerancia_solape_hectareas' => '0.00']);

    $trabajo = trabajoParaCobertura('20.00', [
        ['hectareas_declaradas' => '20.01'],
    ]);

    expect(coberturaDe($trabajo))->toBe(EstadoCoberturaTrabajo::Observado);
});

it('una sesión anulada (rechazada) no cuenta para la cobertura', function () {
    $trabajo = trabajoParaCobertura('20.00', [
        ['hectareas_declaradas' => '25.00', 'anulada_en' => now()],
    ]);

    expect(coberturaDe($trabajo))->toBeNull();
});

it('un trabajo sin ninguna sesión no dispara ninguna cobertura', function () {
    $trabajo = trabajoParaCobertura('20.00');

    expect(coberturaDe($trabajo))->toBeNull();
});

it('un lote inexistente (soft-deleted) deja la cobertura sin calcular, sin romper', function () {
    $trabajo = trabajoParaCobertura('20.00', [
        ['hectareas_declaradas' => '8.00', 'motivo_cierre' => 'relevo_piloto'],
    ]);
    Lote::query()->findOrFail($trabajo->lote_id)->delete();

    expect(coberturaDe($trabajo))->toBeNull();
});

it('un lote de 0.00 hectáreas sin ninguna sesión se considera "completo" por definición (0 >= 0) — caso de datos inválidos en la práctica, no de negocio', function () {
    $trabajo = trabajoParaCobertura('0.00');

    expect(coberturaDe($trabajo))->toBe(EstadoCoberturaTrabajo::Completo);
});

it('la cobertura se recalcula desde los registros de origen, no queda pegada a un valor cacheado', function () {
    $trabajo = trabajoParaCobertura('20.00', [
        ['hectareas_declaradas' => '8.00', 'motivo_cierre' => 'relevo_piloto'],
    ]);

    expect(coberturaDe($trabajo))->toBe(EstadoCoberturaTrabajo::Parcial);

    $piloto = PerPersona::create(['nombre' => 'Piloto entrante', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    Sesion::create([
        'uuid_cliente' => (string) Str::uuid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 2,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '12.00',
        'estado' => EstadoSesion::Cerrado,
        'inicio' => '2026-09-01T10:05:00-04:00',
        'fin' => '2026-09-01T11:05:00-04:00',
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => (string) Str::uuid(),
    ]);

    expect(coberturaDe($trabajo->fresh()))->toBe(EstadoCoberturaTrabajo::Completo);
});
