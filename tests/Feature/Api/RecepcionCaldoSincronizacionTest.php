<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\RecepcionCaldo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * POST /api/sync — recepción de caldo (espec §7.2, HU-10 redefinida por
 * CR-01, tarea 18): litros recibidos por trabajo (uno o varios eventos),
 * litros consumidos por sesión y sobrante al cierre del trabajo, con cuadre
 * recalculable (`recibido == consumido + sobrante`, espec §7.3, criterio de
 * aceptación 4). Ningún dato de composición del caldo — §7.1 lo excluye.
 *
 * Nombres de función propios (no se reutilizan los de otros test files de
 * sync, ver docblock de `CierreSincronizacionTest.php`).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(SecUser::factory()->create(), 'sanctum');
});

function ordenParaRecepcion(): OrdenAplicacion
{
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');

    return OrdenAplicacion::query()
        ->where('lote_id', $loteId)
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();
}

function pilotoParaRecepcion(): PerPersona
{
    return PerPersona::query()->create(['nombre' => 'Piloto Recepción', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
}

/** @return array<string, mixed> */
function registroTrabajoParaRecepcion(string $uuidCliente): array
{
    $orden = ordenParaRecepcion();

    return [
        'tipo' => 'trabajo',
        'uuid_cliente' => $uuidCliente,
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => $orden->nro_aplicacion,
        'inicio' => '2026-09-01T08:00:00-04:00',
    ];
}

/** @return array<string, mixed> */
function registroSesionParaRecepcion(string $uuidCliente, string $trabajoUuidCliente, int $pilotoId, int $secuencia = 1): array
{
    return [
        'tipo' => 'sesion',
        'uuid_cliente' => $uuidCliente,
        'trabajo_uuid_cliente' => $trabajoUuidCliente,
        'secuencia' => $secuencia,
        'piloto_id' => $pilotoId,
        'inicio' => '2026-09-01T08:10:00-04:00',
    ];
}

/** @return array<string, mixed> */
function registroRecepcionSync(string $uuidCliente, string $trabajoUuidCliente, array $sobrescribir = []): array
{
    return array_merge([
        'tipo' => 'recepcion_caldo',
        'uuid_cliente' => $uuidCliente,
        'trabajo_uuid_cliente' => $trabajoUuidCliente,
        'litros' => '150.00',
        'entregado_por' => 'Ing. Agr. del cliente',
        'hora' => '2026-09-01T07:45:00-04:00',
    ], $sobrescribir);
}

/** @return array<string, mixed> */
function registroCierreSesionConLitros(string $uuidCliente, string $sesionUuidCliente, string $litrosConsumidos, string $hectareasDeclaradas = '10.00', string $fin = '2026-09-01T09:00:00-04:00'): array
{
    return [
        'tipo' => 'cierre_sesion',
        'uuid_cliente' => $uuidCliente,
        'sesion_uuid_cliente' => $sesionUuidCliente,
        'fin' => $fin,
        'motivo_cierre' => 'completado',
        'hectareas_declaradas' => $hectareasDeclaradas,
        'litros_consumidos' => $litrosConsumidos,
    ];
}

/** Evidencia `imagen_campo` (HU-09, tarea 21: "sin captura no cierra"), requisito de `cierre_trabajo`. */
function evidenciaImagenCampoParaRecepcion(string $id): string
{
    $uuidCliente = "uuid-evidencia-{$id}";

    Evidencia::query()->create([
        'uuid_cliente' => $uuidCliente,
        'tipo' => TipoEvidencia::ImagenCampo,
        'archivo_url' => "evidencias/imagen_campo/2026/09/{$uuidCliente}.jpg",
        'hash' => hash('sha256', $uuidCliente),
        'fecha' => '2026-09-01T09:00:00-04:00',
    ]);

    return $uuidCliente;
}

/** @return array<string, mixed> */
function registroCierreTrabajoConSobrante(string $uuidCliente, string $trabajoUuidCliente, string $litrosSobrante, string $fin = '2026-09-01T10:00:00-04:00'): array
{
    return [
        'tipo' => 'cierre_trabajo',
        'uuid_cliente' => $uuidCliente,
        'trabajo_uuid_cliente' => $trabajoUuidCliente,
        'fin' => $fin,
        'litros_sobrante' => $litrosSobrante,
        'evidencia_imagen_campo_uuid_cliente' => evidenciaImagenCampoParaRecepcion($uuidCliente),
    ];
}

/*
 * ── Criterio de aceptación 1: litros recibidos (uno o varios eventos) queda
 *    persistido y es consultable ──
 */

it('registra litros recibidos por un trabajo y queda persistido', function () {
    $this->postJson('/api/sync', ['registros' => [registroTrabajoParaRecepcion('uuid-t1')]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroRecepcionSync('uuid-recep-1', 'uuid-t1'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0'))->toBe([
        'uuid_cliente' => 'uuid-recep-1', 'tipo' => 'recepcion_caldo', 'estado' => 'aplicado',
    ]);

    $trabajo = Trabajo::query()->where('uuid_cliente', 'uuid-t1')->firstOrFail();
    $recepcion = RecepcionCaldo::query()->where('uuid_cliente', 'uuid-recep-1')->firstOrFail();

    expect($recepcion->trabajo_id)->toBe($trabajo->id)
        ->and($recepcion->litros)->toBe('150.00')
        ->and($recepcion->entregado_por)->toBe('Ing. Agr. del cliente');
});

it('registra VARIOS eventos de recepción para el mismo trabajo', function () {
    $this->postJson('/api/sync', ['registros' => [registroTrabajoParaRecepcion('uuid-t2')]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroRecepcionSync('uuid-recep-2a', 'uuid-t2', ['litros' => '100.00']),
        registroRecepcionSync('uuid-recep-2b', 'uuid-t2', ['litros' => '50.00']),
    ]])->assertOk();

    foreach ($respuesta->json('resultados') as $resultado) {
        expect($resultado['estado'])->toBe('aplicado');
    }

    $trabajo = Trabajo::query()->where('uuid_cliente', 'uuid-t2')->firstOrFail();
    expect(RecepcionCaldo::query()->where('trabajo_id', $trabajo->id)->count())->toBe(2);
});

it('la recepción puede llegar en el mismo lote que la apertura de su trabajo', function () {
    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroRecepcionSync('uuid-recep-3', 'uuid-t3'),
        registroTrabajoParaRecepcion('uuid-t3'),
    ]])->assertOk();

    foreach ($respuesta->json('resultados') as $resultado) {
        expect($resultado['estado'])->toBe('aplicado');
    }

    expect(RecepcionCaldo::query()->where('uuid_cliente', 'uuid-recep-3')->exists())->toBeTrue();
});

it('una recepción que referencia un trabajo inexistente se rechaza sin romper el resto del lote', function () {
    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroRecepcionSync('uuid-recep-fantasma', 'uuid-trabajo-que-no-existe'),
        registroTrabajoParaRecepcion('uuid-t4'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.1.estado'))->toBe('aplicado');
});

/*
 * ── Criterio de aceptación 2: litros consumidos por sesión se asocia a la
 *    sesión correcta ──
 */

it('registra litros consumidos al cerrar una sesión y los asocia a la sesión correcta', function () {
    $piloto = pilotoParaRecepcion();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaRecepcion('uuid-t5'),
        registroSesionParaRecepcion('uuid-s5a', 'uuid-t5', $piloto->id, 1),
        registroSesionParaRecepcion('uuid-s5b', 'uuid-t5', $piloto->id, 2),
    ]])->assertOk();

    $this->postJson('/api/sync', ['registros' => [
        registroCierreSesionConLitros('uuid-cierre-s5a', 'uuid-s5a', '60.00'),
    ]])->assertOk();

    $sesionA = Sesion::query()->where('uuid_cliente', 'uuid-s5a')->firstOrFail();
    $sesionB = Sesion::query()->where('uuid_cliente', 'uuid-s5b')->firstOrFail();

    expect($sesionA->litros_consumidos)->toBe('60.00')
        ->and($sesionB->litros_consumidos)->toBeNull();
});

/*
 * ── Criterio de aceptación 3: sobrante al cierre del trabajo queda persistido ──
 */

it('registra el sobrante al cerrar el trabajo y queda persistido', function () {
    $this->postJson('/api/sync', ['registros' => [registroTrabajoParaRecepcion('uuid-t6')]])->assertOk();

    $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajoConSobrante('uuid-cierre-t6', 'uuid-t6', '25.00'),
    ]])->assertOk();

    expect(Trabajo::query()->where('uuid_cliente', 'uuid-t6')->firstOrFail()->litros_sobrante)->toBe('25.00');
});

/*
 * ── Criterio de aceptación 4: cuadre recalculable (recibido = consumido + sobrante) ──
 */

it('el cuadre de caldo de un trabajo recalcula recibido = consumido + sobrante desde los registros de origen', function () {
    $piloto = pilotoParaRecepcion();

    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaRecepcion('uuid-t7'),
        registroRecepcionSync('uuid-recep-7a', 'uuid-t7', ['litros' => '150.00']),
        registroRecepcionSync('uuid-recep-7b', 'uuid-t7', ['litros' => '50.00']),
        registroSesionParaRecepcion('uuid-s7a', 'uuid-t7', $piloto->id, 1),
        registroSesionParaRecepcion('uuid-s7b', 'uuid-t7', $piloto->id, 2),
    ]])->assertOk();

    $this->postJson('/api/sync', ['registros' => [
        registroCierreSesionConLitros('uuid-cierre-s7a', 'uuid-s7a', '120.00'),
        registroCierreSesionConLitros('uuid-cierre-s7b', 'uuid-s7b', '65.00'),
    ]])->assertOk();

    $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajoConSobrante('uuid-cierre-t7', 'uuid-t7', '15.00'),
    ]])->assertOk();

    $trabajo = Trabajo::query()->where('uuid_cliente', 'uuid-t7')->firstOrFail();
    $cuadre = $trabajo->cuadreCaldo();

    expect($cuadre)->toBe([
        'recibido' => '200.00',
        'consumido' => '185.00',
        'sobrante' => '15.00',
        'cuadra' => true,
    ]);
});

/*
 * ── Criterio de aceptación 5: reintento del mismo uuid_cliente en
 *    cualquiera de los tres eventos no duplica ──
 */

it('reintentar el mismo registro de recepción responde duplicado sin crear una segunda fila', function () {
    $this->postJson('/api/sync', ['registros' => [registroTrabajoParaRecepcion('uuid-t8')]])->assertOk();

    $recepcion = ['registros' => [registroRecepcionSync('uuid-recep-8', 'uuid-t8')]];
    $this->postJson('/api/sync', $recepcion)->assertOk();
    $segunda = $this->postJson('/api/sync', $recepcion)->assertOk();

    expect($segunda->json('resultados.0.estado'))->toBe('duplicado')
        ->and(RecepcionCaldo::query()->where('uuid_cliente', 'uuid-recep-8')->count())->toBe(1);
});

it('reintentar el mismo cierre de sesión con litros_consumidos responde duplicado sin recerrar ni cambiar el valor', function () {
    $piloto = pilotoParaRecepcion();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaRecepcion('uuid-t9'),
        registroSesionParaRecepcion('uuid-s9', 'uuid-t9', $piloto->id),
    ]])->assertOk();

    $cierre = ['registros' => [registroCierreSesionConLitros('uuid-cierre-s9', 'uuid-s9', '40.00')]];
    $this->postJson('/api/sync', $cierre)->assertOk();
    $segunda = $this->postJson('/api/sync', $cierre)->assertOk();

    expect($segunda->json('resultados.0.estado'))->toBe('duplicado')
        ->and(Sesion::query()->where('uuid_cliente', 'uuid-s9')->firstOrFail()->litros_consumidos)->toBe('40.00');
});

it('reintentar el mismo cierre de trabajo con litros_sobrante responde duplicado sin recerrar ni cambiar el valor', function () {
    $this->postJson('/api/sync', ['registros' => [registroTrabajoParaRecepcion('uuid-t10')]])->assertOk();

    $cierre = ['registros' => [registroCierreTrabajoConSobrante('uuid-cierre-t10', 'uuid-t10', '30.00')]];
    $this->postJson('/api/sync', $cierre)->assertOk();
    $segunda = $this->postJson('/api/sync', $cierre)->assertOk();

    expect($segunda->json('resultados.0.estado'))->toBe('duplicado')
        ->and(Trabajo::query()->where('uuid_cliente', 'uuid-t10')->firstOrFail()->litros_sobrante)->toBe('30.00');
});
