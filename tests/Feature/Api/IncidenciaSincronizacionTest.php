<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Incidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * POST /api/sync — incidencias de sesión con foto obligatoria (HU-08, tarea
 * 22; espec §4.3, fila `incidencias`; CA literal: "incidencia offline con
 * evidencia comprimida, ligada a la sesión"). Extiende
 * `EscrituraSincronizacionEloquent::registrarIncidencia()`, mismo patrón que
 * `registrarCondiciones()` (crea fila nueva, idempotencia vía `UNIQUE`) y que
 * la evidencia obligatoria de `cerrarTrabajo()` (tarea 21).
 *
 * El campo del catálogo de incidencia viaja en el JSON como `tipo_incidencia`,
 * no `tipo` (ver docblock de `Contratos/RegistroIncidencia`: `tipo` ya es la
 * key que usa el "sobre" del lote para el tipo de REGISTRO).
 *
 * Nombres de función propios (no se reutilizan los de otros test files de
 * sync — Pest declara los helpers de cada archivo como funciones PHP
 * globales, ver docblock de `CierreSincronizacionTest.php`).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(SecUser::factory()->create(), 'sanctum');
});

function ordenParaIncidencia(): OrdenAplicacion
{
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');

    return OrdenAplicacion::query()
        ->whereHas('ordenLotes', fn ($q) => $q->where('lote_id', $loteId))
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();
}

function pilotoParaIncidencia(): PerPersona
{
    return PerPersona::query()->create(['nombre' => 'Piloto Incidencia', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
}

/** @return array<string, mixed> */
function registroTrabajoParaIncidencia(string $uuidCliente): array
{
    $orden = ordenParaIncidencia();

    return [
        'tipo' => 'trabajo',
        'uuid_cliente' => $uuidCliente,
        'orden_id' => $orden->id,
        'lote_id' => (int) $orden->ordenLotes()->value('lote_id'),
        'nro_aplicacion' => $orden->nro_aplicacion,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ];
}

/** @return array<string, mixed> */
function registroSesionParaIncidencia(string $uuidCliente, string $trabajoUuidCliente, int $pilotoId): array
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

/** Sesión abierta lista para referenciar desde un registro de incidencia. */
function sesionAbiertaParaIncidencia(string $sufijo): string
{
    $piloto = pilotoParaIncidencia();
    $trabajoUuid = "uuid-trabajo-incidencia-{$sufijo}";
    $sesionUuid = "uuid-sesion-incidencia-{$sufijo}";

    test()->postJson('/api/sync', ['registros' => [
        registroTrabajoParaIncidencia($trabajoUuid),
        registroSesionParaIncidencia($sesionUuid, $trabajoUuid, $piloto->id),
    ]])->assertOk();

    return $sesionUuid;
}

/** Evidencia `foto_incidencia`, lista para referenciar desde un registro de incidencia. */
function evidenciaFotoIncidencia(string $sufijo): string
{
    $uuidCliente = "uuid-evidencia-incidencia-{$sufijo}";

    Evidencia::query()->create([
        'uuid_cliente' => $uuidCliente,
        'tipo' => TipoEvidencia::FotoIncidencia,
        'archivo_url' => "evidencias/foto_incidencia/2026/09/{$uuidCliente}.jpg",
        'hash' => hash('sha256', $uuidCliente),
        'fecha' => '2026-09-01T09:00:00-04:00',
    ]);

    return $uuidCliente;
}

/** Evidencia de un tipo DISTINTO de `foto_incidencia` — para el caso "tipo incorrecto". */
function evidenciaTipoDistintoParaIncidencia(string $sufijo): string
{
    $uuidCliente = "uuid-evidencia-incidencia-tipo-distinto-{$sufijo}";

    Evidencia::query()->create([
        'uuid_cliente' => $uuidCliente,
        'tipo' => TipoEvidencia::ImagenCampo,
        'archivo_url' => "evidencias/imagen_campo/2026/09/{$uuidCliente}.jpg",
        'hash' => hash('sha256', $uuidCliente),
        'fecha' => '2026-09-01T09:00:00-04:00',
    ]);

    return $uuidCliente;
}

/*
 * ── Caso 1: sin evidencia_foto_uuid_cliente → rechazado ──
 */

it('registrar una incidencia SIN evidencia_foto_uuid_cliente se rechaza', function () {
    $sesionUuid = sesionAbiertaParaIncidencia('sin-ev');

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        [
            'tipo' => 'incidencia',
            'uuid_cliente' => 'uuid-incidencia-sin-ev',
            'sesion_uuid_cliente' => $sesionUuid,
            'tipo_incidencia' => 'mecanica',
            'descripcion' => 'aterrizaje forzoso por falla de motor',
            'hora' => '2026-09-01T10:20:00-04:00',
        ],
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado');
    expect(Incidencia::query()->where('uuid_cliente', 'uuid-incidencia-sin-ev')->exists())->toBeFalse();
});

/*
 * ── Caso 2: evidencia existente pero de tipo distinto (imagen_campo) → rechazado ──
 */

it('registrar una incidencia con una evidencia de tipo distinto de foto_incidencia se rechaza', function () {
    $sesionUuid = sesionAbiertaParaIncidencia('tipo-malo');
    $evidenciaUuid = evidenciaTipoDistintoParaIncidencia('tipo-malo');

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        [
            'tipo' => 'incidencia',
            'uuid_cliente' => 'uuid-incidencia-tipo-malo',
            'sesion_uuid_cliente' => $sesionUuid,
            'tipo_incidencia' => 'mecanica',
            'hora' => '2026-09-01T10:20:00-04:00',
            'evidencia_foto_uuid_cliente' => $evidenciaUuid,
        ],
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull();
    expect(Incidencia::query()->where('uuid_cliente', 'uuid-incidencia-tipo-malo')->exists())->toBeFalse();
});

/*
 * ── Caso 3: evidencia inexistente → rechazado ──
 */

it('registrar una incidencia referenciando una evidencia inexistente se rechaza', function () {
    $sesionUuid = sesionAbiertaParaIncidencia('ev-fantasma');

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        [
            'tipo' => 'incidencia',
            'uuid_cliente' => 'uuid-incidencia-ev-fantasma',
            'sesion_uuid_cliente' => $sesionUuid,
            'tipo_incidencia' => 'mecanica',
            'hora' => '2026-09-01T10:20:00-04:00',
            'evidencia_foto_uuid_cliente' => 'uuid-evidencia-que-no-existe',
        ],
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull();
    expect(Incidencia::query()->where('uuid_cliente', 'uuid-incidencia-ev-fantasma')->exists())->toBeFalse();
});

/*
 * ── Caso 4: sesion_uuid_cliente que no existe → rechazado ──
 */

it('registrar una incidencia referenciando una sesión inexistente se rechaza', function () {
    $evidenciaUuid = evidenciaFotoIncidencia('sesion-fantasma');

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        [
            'tipo' => 'incidencia',
            'uuid_cliente' => 'uuid-incidencia-sesion-fantasma',
            'sesion_uuid_cliente' => 'uuid-sesion-que-no-existe',
            'tipo_incidencia' => 'mecanica',
            'hora' => '2026-09-01T10:20:00-04:00',
            'evidencia_foto_uuid_cliente' => $evidenciaUuid,
        ],
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull();
    expect(Incidencia::query()->where('uuid_cliente', 'uuid-incidencia-sesion-fantasma')->exists())->toBeFalse();
});

/*
 * ── Caso 5: incidencia válida → aplicado, fila persistida con ids reales ──
 */

it('registrar una incidencia válida se aplica y persiste con evidencia_foto_id y sesion_id correctos', function () {
    $sesionUuid = sesionAbiertaParaIncidencia('ok');
    $evidenciaUuid = evidenciaFotoIncidencia('ok');

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        [
            'tipo' => 'incidencia',
            'uuid_cliente' => 'uuid-incidencia-ok',
            'sesion_uuid_cliente' => $sesionUuid,
            'tipo_incidencia' => 'esc',
            'descripcion' => 'ESC recalentado, se detiene el vuelo',
            'hora' => '2026-09-01T10:20:00-04:00',
            'evidencia_foto_uuid_cliente' => $evidenciaUuid,
        ],
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('aplicado');

    $sesion = Sesion::query()->where('uuid_cliente', $sesionUuid)->firstOrFail();
    $evidencia = Evidencia::query()->where('uuid_cliente', $evidenciaUuid)->firstOrFail();
    $incidencia = Incidencia::query()->where('uuid_cliente', 'uuid-incidencia-ok')->firstOrFail();

    expect($incidencia->sesion_id)->toBe($sesion->id)
        ->and($incidencia->evidencia_foto_id)->toBe($evidencia->id)
        ->and($incidencia->tipo->value)->toBe('esc')
        ->and($incidencia->descripcion)->toBe('ESC recalentado, se detiene el vuelo');
});

/*
 * ── Caso 6: reintento idempotente del mismo uuid_cliente → duplicado, sin duplicar la fila ──
 */

it('reintentar el mismo registro de incidencia responde duplicado sin crear una segunda fila', function () {
    $sesionUuid = sesionAbiertaParaIncidencia('idem');
    $evidenciaUuid = evidenciaFotoIncidencia('idem');

    $incidencia = ['registros' => [
        [
            'tipo' => 'incidencia',
            'uuid_cliente' => 'uuid-incidencia-idem',
            'sesion_uuid_cliente' => $sesionUuid,
            'tipo_incidencia' => 'clima',
            'hora' => '2026-09-01T10:20:00-04:00',
            'evidencia_foto_uuid_cliente' => $evidenciaUuid,
        ],
    ]];

    $primera = $this->postJson('/api/sync', $incidencia)->assertOk();
    $segunda = $this->postJson('/api/sync', $incidencia)->assertOk();

    expect($primera->json('resultados.0.estado'))->toBe('aplicado')
        ->and($segunda->json('resultados.0.estado'))->toBe('duplicado')
        ->and(Incidencia::query()->where('uuid_cliente', 'uuid-incidencia-idem')->count())->toBe(1);
});

/*
 * ── Caso 7: misma evidencia referenciada por dos incidencias distintas → la segunda se rechaza, la primera sigue intacta ──
 */

it('referenciar la MISMA evidencia desde dos incidencias distintas rechaza la segunda y no toca la primera', function () {
    $sesionUuid = sesionAbiertaParaIncidencia('reuso');
    $evidenciaCompartida = evidenciaFotoIncidencia('reuso');

    $primera = $this->postJson('/api/sync', ['registros' => [
        [
            'tipo' => 'incidencia',
            'uuid_cliente' => 'uuid-incidencia-reuso-a',
            'sesion_uuid_cliente' => $sesionUuid,
            'tipo_incidencia' => 'bateria',
            'hora' => '2026-09-01T10:20:00-04:00',
            'evidencia_foto_uuid_cliente' => $evidenciaCompartida,
        ],
    ]])->assertOk();

    $segunda = $this->postJson('/api/sync', ['registros' => [
        [
            'tipo' => 'incidencia',
            'uuid_cliente' => 'uuid-incidencia-reuso-b',
            'sesion_uuid_cliente' => $sesionUuid,
            'tipo_incidencia' => 'bateria',
            'hora' => '2026-09-01T10:25:00-04:00',
            'evidencia_foto_uuid_cliente' => $evidenciaCompartida,
        ],
    ]])->assertOk();

    expect($primera->json('resultados.0.estado'))->toBe('aplicado')
        ->and($segunda->json('resultados.0.estado'))->toBe('rechazado')
        ->and($segunda->json('resultados.0.motivo'))->not->toBeNull();

    expect(Incidencia::query()->where('uuid_cliente', 'uuid-incidencia-reuso-a')->exists())->toBeTrue()
        ->and(Incidencia::query()->where('uuid_cliente', 'uuid-incidencia-reuso-b')->exists())->toBeFalse();
});
