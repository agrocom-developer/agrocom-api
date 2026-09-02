<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * POST /api/sync — cierre de trabajo/sesión (HU-05, tarea 13):
 * `cierre_trabajo`/`cierre_sesion`, idempotencia sobre una fila EXISTENTE
 * (mecanismo distinto al de apertura, ver runs/13.md) y verificación de
 * pertenencia (mismo criterio que la tarea 12 le agregó a la apertura,
 * extendido: un trabajo no tiene piloto propio, así que "es del operario" se
 * resuelve por participación vía sus sesiones, no por dueño directo).
 *
 * Nombres de función propios (no se reutilizan los de SincronizarLoteTest.php
 * ni ReplaySincronizacionTest.php): Pest declara los helpers de cada archivo
 * como funciones PHP globales, y reusar un nombre entre archivos choca con
 * "cannot redeclare".
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(SecUser::factory()->create(), 'sanctum');
});

function ordenParaCierre(): OrdenAplicacion
{
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');

    return OrdenAplicacion::query()
        ->where('lote_id', $loteId)
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();
}

function pilotoParaCierre(string $nombre = 'Piloto Cierre'): PerPersona
{
    return PerPersona::query()->create(['nombre' => $nombre, 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
}

/** @return array<string, mixed> */
function registroTrabajoParaCierre(string $uuidCliente): array
{
    $orden = ordenParaCierre();

    return [
        'tipo' => 'trabajo',
        'uuid_cliente' => $uuidCliente,
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => $orden->nro_aplicacion,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ];
}

/** @return array<string, mixed> */
function registroSesionParaCierre(string $uuidCliente, string $trabajoUuidCliente, int $pilotoId): array
{
    return [
        'tipo' => 'sesion',
        'uuid_cliente' => $uuidCliente,
        'trabajo_uuid_cliente' => $trabajoUuidCliente,
        'secuencia' => 1,
        'piloto_id' => $pilotoId,
        'inicio' => '2026-09-01T10:05:00-04:00',
    ];
}

/**
 * Crea una evidencia `imagen_campo` (HU-09, tarea 21: "sin captura no
 * cierra") y devuelve su `uuid_cliente`, listo para pasar a
 * `registroCierreTrabajo()` — mismo criterio que `pilotoParaCierre()`, un
 * helper que persiste el fixture que el registro de sync va a referenciar.
 */
function evidenciaImagenCampoParaCierre(string $sufijo): string
{
    $uuidCliente = "uuid-evidencia-{$sufijo}";

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
function registroCierreTrabajo(string $uuidCliente, string $trabajoUuidCliente, string $evidenciaImagenCampoUuidCliente, string $fin = '2026-09-01T12:00:00-04:00'): array
{
    return [
        'tipo' => 'cierre_trabajo',
        'uuid_cliente' => $uuidCliente,
        'trabajo_uuid_cliente' => $trabajoUuidCliente,
        'fin' => $fin,
        'evidencia_imagen_campo_uuid_cliente' => $evidenciaImagenCampoUuidCliente,
    ];
}

/** @return array<string, mixed> */
function registroCierreSesion(string $uuidCliente, string $sesionUuidCliente, string $motivo = 'completado', string $fin = '2026-09-01T12:00:00-04:00', string $hectareasDeclaradas = '18.40'): array
{
    return [
        'tipo' => 'cierre_sesion',
        'uuid_cliente' => $uuidCliente,
        'sesion_uuid_cliente' => $sesionUuidCliente,
        'fin' => $fin,
        'motivo_cierre' => $motivo,
        'hectareas_declaradas' => $hectareasDeclaradas,
    ];
}

/*
 * ── Criterio de aceptación 1: cerrar deja abierto -> cerrado, con fin/motivo ──
 */

it('cierra un trabajo abierto y lo deja cerrado, con fin seteado', function () {
    $piloto = pilotoParaCierre();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t1'),
        registroSesionParaCierre('uuid-s1', 'uuid-t1', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajo('uuid-cierre-t1', 'uuid-t1', evidenciaImagenCampoParaCierre('t1')),
    ]])->assertOk();

    expect($respuesta->json('resultados.0'))->toBe([
        'uuid_cliente' => 'uuid-cierre-t1', 'tipo' => 'cierre_trabajo', 'estado' => 'aplicado',
    ]);

    $trabajo = Trabajo::query()->where('uuid_cliente', 'uuid-t1')->firstOrFail();
    expect($trabajo->estado)->toBe(EstadoTrabajo::Cerrado)
        ->and($trabajo->cierre_uuid_cliente)->toBe('uuid-cierre-t1')
        // `->timestamp` (instante real), no un match de string sobre la hora
        // local: la tarea 29 normaliza `inicio`/`fin` a UTC antes de
        // persistir (ver `MaquinaEstadosTrabajo::normalizarUtc()`), mismo
        // criterio que ya usa `ActaConformidadTest.php` para `fecha_firma`.
        ->and($trabajo->fin?->timestamp)->toBe(strtotime('2026-09-01T12:00:00-04:00'));
});

it('cierra una sesión abierta y la deja cerrada, con fin, motivo_cierre y hectareas_declaradas persistidos', function () {
    $piloto = pilotoParaCierre();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t2'),
        registroSesionParaCierre('uuid-s2', 'uuid-t2', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreSesion('uuid-cierre-s2', 'uuid-s2', 'relevo_piloto', hectareasDeclaradas: '9.75'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('aplicado');

    $sesion = Sesion::query()->where('uuid_cliente', 'uuid-s2')->firstOrFail();
    expect($sesion->estado)->toBe(EstadoSesion::Cerrado)
        ->and($sesion->cierre_uuid_cliente)->toBe('uuid-cierre-s2')
        ->and($sesion->motivo_cierre)->toBe('relevo_piloto')
        ->and($sesion->hectareas_declaradas)->toBe('9.75')
        ->and($sesion->fin?->timestamp)->toBe(strtotime('2026-09-01T12:00:00-04:00'));
});

it('cerrar una sesión sin hectareas_declaradas se rechaza', function () {
    $piloto = pilotoParaCierre();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t2b'),
        registroSesionParaCierre('uuid-s2b', 'uuid-t2b', $piloto->id),
    ]])->assertOk();

    $sinHectareas = registroCierreSesion('uuid-cierre-s2b', 'uuid-s2b');
    unset($sinHectareas['hectareas_declaradas']);

    $respuesta = $this->postJson('/api/sync', ['registros' => [$sinHectareas]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado');
    expect(Sesion::query()->where('uuid_cliente', 'uuid-s2b')->firstOrFail()->estado)->toBe(EstadoSesion::Abierto);
});

it('cerrar la sesión de un trabajo actualiza hectareas_declaradas del trabajo con la suma de sus sesiones', function () {
    $piloto = pilotoParaCierre();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t2c'),
        registroSesionParaCierre('uuid-s2c-1', 'uuid-t2c', $piloto->id),
        [...registroSesionParaCierre('uuid-s2c-2', 'uuid-t2c', $piloto->id), 'secuencia' => 2],
    ]])->assertOk();

    $this->postJson('/api/sync', ['registros' => [
        registroCierreSesion('uuid-cierre-s2c-1', 'uuid-s2c-1', hectareasDeclaradas: '12.00'),
    ]])->assertOk();

    expect(Trabajo::query()->where('uuid_cliente', 'uuid-t2c')->firstOrFail()->hectareas_declaradas)->toBe('12.00');

    $this->postJson('/api/sync', ['registros' => [
        registroCierreSesion('uuid-cierre-s2c-2', 'uuid-s2c-2', hectareasDeclaradas: '6.40'),
    ]])->assertOk();

    expect(Trabajo::query()->where('uuid_cliente', 'uuid-t2c')->firstOrFail()->hectareas_declaradas)->toBe('18.40');
});

it('un cierre_trabajo y un cierre_sesion pueden llegar en el mismo lote que su propia apertura', function () {
    $piloto = pilotoParaCierre();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t3'),
        registroSesionParaCierre('uuid-s3', 'uuid-t3', $piloto->id),
        registroCierreSesion('uuid-cierre-s3', 'uuid-s3'),
        registroCierreTrabajo('uuid-cierre-t3', 'uuid-t3', evidenciaImagenCampoParaCierre('t3')),
    ]])->assertOk();

    foreach ($respuesta->json('resultados') as $resultado) {
        expect($resultado['estado'])->toBe('aplicado');
    }

    expect(Trabajo::query()->where('uuid_cliente', 'uuid-t3')->firstOrFail()->estado)->toBe(EstadoTrabajo::Cerrado)
        ->and(Sesion::query()->where('uuid_cliente', 'uuid-s3')->firstOrFail()->estado)->toBe(EstadoSesion::Cerrado);
});

/*
 * ── Criterio de aceptación 2: reintentar el mismo cierre es idempotente ──
 */

it('reintentar el mismo cierre de trabajo responde duplicado sin romper ni recerrar', function () {
    $piloto = pilotoParaCierre();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t4'),
        registroSesionParaCierre('uuid-s4', 'uuid-t4', $piloto->id),
    ]])->assertOk();

    $cierre = ['registros' => [registroCierreTrabajo('uuid-cierre-t4', 'uuid-t4', evidenciaImagenCampoParaCierre('t4'))]];
    $this->postJson('/api/sync', $cierre)->assertOk();
    $segunda = $this->postJson('/api/sync', $cierre)->assertOk();

    expect($segunda->json('resultados.0.estado'))->toBe('duplicado')
        ->and(Trabajo::query()->where('uuid_cliente', 'uuid-t4')->count())->toBe(1);
});

it('reintentar el mismo cierre de sesión responde duplicado sin romper ni recerrar', function () {
    $piloto = pilotoParaCierre();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t5'),
        registroSesionParaCierre('uuid-s5', 'uuid-t5', $piloto->id),
    ]])->assertOk();

    $cierre = ['registros' => [registroCierreSesion('uuid-cierre-s5', 'uuid-s5')]];
    $this->postJson('/api/sync', $cierre)->assertOk();
    $segunda = $this->postJson('/api/sync', $cierre)->assertOk();

    expect($segunda->json('resultados.0.estado'))->toBe('duplicado')
        ->and(Sesion::query()->where('uuid_cliente', 'uuid-s5')->count())->toBe(1);
});

it('el mismo lote de cierre aplicado 10 veces deja un estado final idéntico', function () {
    $piloto = pilotoParaCierre();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t-replay'),
        registroSesionParaCierre('uuid-s-replay', 'uuid-t-replay', $piloto->id),
    ]])->assertOk();

    $cierre = ['registros' => [
        registroCierreSesion('uuid-cierre-s-replay', 'uuid-s-replay'),
        registroCierreTrabajo('uuid-cierre-t-replay', 'uuid-t-replay', evidenciaImagenCampoParaCierre('replay')),
    ]];

    foreach (range(1, 10) as $intento) {
        $respuesta = $this->postJson('/api/sync', $cierre)->assertOk();

        $estadoEsperado = $intento === 1 ? 'aplicado' : 'duplicado';
        foreach ($respuesta->json('resultados') as $resultado) {
            expect($resultado['estado'])->toBe($estadoEsperado);
        }
    }

    expect(Trabajo::query()->count())->toBe(1)
        ->and(Sesion::query()->count())->toBe(1)
        ->and(Trabajo::query()->sole()->estado)->toBe(EstadoTrabajo::Cerrado)
        ->and(Sesion::query()->sole()->estado)->toBe(EstadoSesion::Cerrado);
});

/*
 * ── Criterio de aceptación 3: cerrar lo ya cerrado, o abrir lo cerrado, se
 *    rechaza por la máquina de estados — nunca un 500 ──
 */

it('cerrar un trabajo ya cerrado con un evento de cierre DISTINTO se rechaza', function () {
    $piloto = pilotoParaCierre();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t6'),
        registroSesionParaCierre('uuid-s6', 'uuid-t6', $piloto->id),
    ]])->assertOk();
    $evidencia = evidenciaImagenCampoParaCierre('t6');
    $this->postJson('/api/sync', ['registros' => [registroCierreTrabajo('uuid-cierre-t6-a', 'uuid-t6', $evidencia)]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajo('uuid-cierre-t6-b', 'uuid-t6', $evidencia, '2026-09-01T15:00:00-04:00'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull();

    // El fin queda el del PRIMER cierre, no el del segundo intento rechazado.
    $trabajo = Trabajo::query()->where('uuid_cliente', 'uuid-t6')->firstOrFail();
    expect($trabajo->cierre_uuid_cliente)->toBe('uuid-cierre-t6-a')
        ->and($trabajo->fin?->timestamp)->toBe(strtotime('2026-09-01T12:00:00-04:00'));
});

it('cerrar una sesión ya cerrada con un evento de cierre DISTINTO se rechaza', function () {
    $piloto = pilotoParaCierre();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t7'),
        registroSesionParaCierre('uuid-s7', 'uuid-t7', $piloto->id),
    ]])->assertOk();
    $this->postJson('/api/sync', ['registros' => [registroCierreSesion('uuid-cierre-s7-a', 'uuid-s7')]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreSesion('uuid-cierre-s7-b', 'uuid-s7', 'otro'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull();

    $sesion = Sesion::query()->where('uuid_cliente', 'uuid-s7')->firstOrFail();
    expect($sesion->cierre_uuid_cliente)->toBe('uuid-cierre-s7-a')
        ->and($sesion->motivo_cierre)->toBe('completado');
});

it('reabrir (mismo uuid_cliente de apertura) un trabajo ya cerrado responde duplicado, no error', function () {
    $piloto = pilotoParaCierre();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t8'),
        registroSesionParaCierre('uuid-s8', 'uuid-t8', $piloto->id),
    ]])->assertOk();
    $this->postJson('/api/sync', ['registros' => [registroCierreTrabajo('uuid-cierre-t8', 'uuid-t8', evidenciaImagenCampoParaCierre('t8'))]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [registroTrabajoParaCierre('uuid-t8')]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('duplicado');
    expect(Trabajo::query()->where('uuid_cliente', 'uuid-t8')->firstOrFail()->estado)->toBe(EstadoTrabajo::Cerrado);
});

it('un cierre_trabajo que referencia un trabajo inexistente se rechaza sin romper el resto del lote', function () {
    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajo('uuid-cierre-fantasma', 'uuid-trabajo-que-no-existe', evidenciaImagenCampoParaCierre('fantasma')),
        registroTrabajoParaCierre('uuid-t9'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.1.estado'))->toBe('aplicado');
});

it('un cierre_sesion que referencia una sesión inexistente se rechaza sin romper el resto del lote', function () {
    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreSesion('uuid-cierre-sesion-fantasma', 'uuid-sesion-que-no-existe'),
        registroTrabajoParaCierre('uuid-t10'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.1.estado'))->toBe('aplicado');
});

it('un cierre_sesion con motivo_cierre fuera del catálogo se rechaza', function () {
    $piloto = pilotoParaCierre();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t11'),
        registroSesionParaCierre('uuid-s11', 'uuid-t11', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreSesion('uuid-cierre-s11', 'uuid-s11', 'motivo-inventado'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado');
    expect(Sesion::query()->where('uuid_cliente', 'uuid-s11')->firstOrFail()->estado)->toBe(EstadoSesion::Abierto);
});

/*
 * ── Criterio de aceptación 4: un token de operario no puede cerrar un
 *    trabajo o sesión que no es suyo (mismo criterio que la tarea 12) ──
 */

it('una sesión cuyo piloto no es el operario del token no puede cerrarla', function () {
    // Se abre con el actor por defecto del beforeEach (sin persona propia:
    // "un null no rechaza nada" en la verificación de pertenencia de la
    // apertura, tarea 12) — así la sesión de $otroPiloto sí llega a existir.
    $otroPiloto = pilotoParaCierre('Piloto ajeno');
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t12'),
        registroSesionParaCierre('uuid-s12', 'uuid-t12', $otroPiloto->id),
    ]])->assertOk();

    $miPersona = PerPersona::query()->create(['nombre' => 'Operario del token', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $this->actingAs(SecUser::factory()->create(['persona_id' => $miPersona->id]), 'sanctum');

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreSesion('uuid-cierre-s12', 'uuid-s12'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull();
    expect(Sesion::query()->where('uuid_cliente', 'uuid-s12')->firstOrFail()->estado)->toBe(EstadoSesion::Abierto);
});

it('una sesión cuyo piloto es el operario del token se cierra igual', function () {
    $miPersona = PerPersona::query()->create(['nombre' => 'Operario del token', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $this->actingAs(SecUser::factory()->create(['persona_id' => $miPersona->id]), 'sanctum');

    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t13'),
        registroSesionParaCierre('uuid-s13', 'uuid-t13', $miPersona->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreSesion('uuid-cierre-s13', 'uuid-s13'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('aplicado');
});

it('un trabajo cuyas sesiones son todas de otro operario no puede cerrarlo', function () {
    $otroPiloto = pilotoParaCierre('Piloto que voló el trabajo');
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t14'),
        registroSesionParaCierre('uuid-s14', 'uuid-t14', $otroPiloto->id),
    ]])->assertOk();

    $miPersona = PerPersona::query()->create(['nombre' => 'Operario ajeno al trabajo', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $this->actingAs(SecUser::factory()->create(['persona_id' => $miPersona->id]), 'sanctum');

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajo('uuid-cierre-t14', 'uuid-t14', evidenciaImagenCampoParaCierre('t14')),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull();
    expect(Trabajo::query()->where('uuid_cliente', 'uuid-t14')->firstOrFail()->estado)->toBe(EstadoTrabajo::Abierto);
});

it('un trabajo con una sesión propia del operario sí puede cerrarlo', function () {
    $miPersona = PerPersona::query()->create(['nombre' => 'Operario dueño de su sesión', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $this->actingAs(SecUser::factory()->create(['persona_id' => $miPersona->id]), 'sanctum');

    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t15'),
        registroSesionParaCierre('uuid-s15', 'uuid-t15', $miPersona->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajo('uuid-cierre-t15', 'uuid-t15', evidenciaImagenCampoParaCierre('t15')),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('aplicado');
});

it('un trabajo TODAVÍA sin sesiones puede cerrarlo cualquier operario legítimo', function () {
    $this->postJson('/api/sync', ['registros' => [registroTrabajoParaCierre('uuid-t16')]])->assertOk();

    $miPersona = PerPersona::query()->create(['nombre' => 'Cualquier operario', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $this->actingAs(SecUser::factory()->create(['persona_id' => $miPersona->id]), 'sanctum');

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajo('uuid-cierre-t16', 'uuid-t16', evidenciaImagenCampoParaCierre('t16')),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('aplicado');
});

/*
 * ── Criterio de aceptación 5 (tarea 29): `inicio`/`fin` persisten como el
 *    INSTANTE UTC real, no la hora local literal ──
 *
 * `ope_trabajos.inicio`/`fin` y `ope_sesiones.inicio`/`fin` son `dateTime`
 * sin timezone (ver `MaquinaEstadosTrabajo::normalizarUtc()`). Un string con
 * offset -04:00 (Bolivia) que entra por `POST /api/sync` tiene que guardarse
 * como su equivalente UTC — round-trip completo (guardado + `->fresh()`),
 * no solo el objeto en memoria antes de guardar.
 */

it('inicio/fin con offset -04:00 persisten como el instante UTC real, no la hora local literal', function () {
    $piloto = pilotoParaCierre('Piloto de timezone');

    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierre('uuid-t-tz'),
        registroSesionParaCierre('uuid-s-tz', 'uuid-t-tz', $piloto->id),
    ]])->assertOk();

    $this->postJson('/api/sync', ['registros' => [
        registroCierreSesion('uuid-cierre-s-tz', 'uuid-s-tz'),
        registroCierreTrabajo('uuid-cierre-t-tz', 'uuid-t-tz', evidenciaImagenCampoParaCierre('tz')),
    ]])->assertOk();

    $trabajo = Trabajo::query()->where('uuid_cliente', 'uuid-t-tz')->firstOrFail()->fresh();
    $sesion = Sesion::query()->where('uuid_cliente', 'uuid-s-tz')->firstOrFail()->fresh();

    // `registroTrabajoParaCierre()`: inicio '2026-09-01T10:00:00-04:00' == '2026-09-01T14:00:00Z'.
    expect($trabajo->inicio?->timestamp)->toBe(strtotime('2026-09-01T10:00:00-04:00'))
        ->and($trabajo->inicio?->timestamp)->toBe(strtotime('2026-09-01T14:00:00Z'))
        // `registroCierreTrabajo()`/`registroCierreSesion()`: fin por defecto
        // '2026-09-01T12:00:00-04:00' == '2026-09-01T16:00:00Z'.
        ->and($trabajo->fin?->timestamp)->toBe(strtotime('2026-09-01T16:00:00Z'))
        // `registroSesionParaCierre()`: inicio '2026-09-01T10:05:00-04:00' == '2026-09-01T14:05:00Z'.
        ->and($sesion->inicio?->timestamp)->toBe(strtotime('2026-09-01T14:05:00Z'))
        ->and($sesion->fin?->timestamp)->toBe(strtotime('2026-09-01T16:00:00Z'));
});
