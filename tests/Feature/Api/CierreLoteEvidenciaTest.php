<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Aplicacion\CalcularCoberturaTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoCoberturaTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Cierre de lote con evidencia obligatoria (HU-09, tarea 21; espec §9/§10:
 * "sin captura no cierra" / "lote conformado sin imagen del campo" es alerta
 * de "sin evidencia"). Extiende `EscrituraSincronizacionEloquent::cerrarTrabajo()`
 * (tarea 13) con la validación de `evidencia_imagen_campo_uuid_cliente`,
 * referenciando una evidencia ya subida por `POST /api/evidencias` (tarea
 * 19, `TipoEvidencia::ImagenCampo`). Reutiliza `CalcularCoberturaTrabajo`
 * (tarea 20) sin reimplementarlo — el único test nuevo sobre esa lógica es de
 * regresión: confirma que el cierre no "limpia" el cálculo de `observado`.
 *
 * Nombres de función propios (ver docblock de `CierreSincronizacionTest.php`
 * sobre por qué: Pest declara los helpers de cada archivo como funciones
 * globales, y reusar un nombre entre archivos choca con "cannot redeclare").
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(SecUser::factory()->create(), 'sanctum');
});

function ordenParaCierreLote(): OrdenAplicacion
{
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');

    return OrdenAplicacion::query()
        ->where('lote_id', $loteId)
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();
}

function pilotoParaCierreLote(): PerPersona
{
    return PerPersona::query()->create(['nombre' => 'Piloto Cierre Lote', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
}

/** @return array<string, mixed> */
function registroTrabajoParaCierreLote(string $uuidCliente): array
{
    $orden = ordenParaCierreLote();

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
function registroSesionParaCierreLote(string $uuidCliente, string $trabajoUuidCliente, int $pilotoId): array
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

/** Evidencia `imagen_campo`, lista para referenciar desde `registroCierreTrabajoParaLote()`. */
function evidenciaImagenCampoParaLote(string $sufijo): string
{
    $uuidCliente = "uuid-evidencia-lote-{$sufijo}";

    Evidencia::query()->create([
        'uuid_cliente' => $uuidCliente,
        'tipo' => TipoEvidencia::ImagenCampo,
        'archivo_url' => "evidencias/imagen_campo/2026/09/{$uuidCliente}.jpg",
        'hash' => hash('sha256', $uuidCliente),
        'fecha' => '2026-09-01T09:00:00-04:00',
    ]);

    return $uuidCliente;
}

/** Evidencia de un tipo DISTINTO de `imagen_campo` — para el caso "tipo incorrecto". */
function evidenciaTipoDistintoParaLote(string $sufijo): string
{
    $uuidCliente = "uuid-evidencia-lote-tipo-distinto-{$sufijo}";

    Evidencia::query()->create([
        'uuid_cliente' => $uuidCliente,
        'tipo' => TipoEvidencia::CapturaRc,
        'archivo_url' => "evidencias/captura_rc/2026/09/{$uuidCliente}.jpg",
        'hash' => hash('sha256', $uuidCliente),
        'fecha' => '2026-09-01T09:00:00-04:00',
    ]);

    return $uuidCliente;
}

/** @return array<string, mixed> */
function registroCierreTrabajoParaLote(string $uuidCliente, string $trabajoUuidCliente, ?string $evidenciaImagenCampoUuidCliente, string $fin = '2026-09-01T12:00:00-04:00'): array
{
    $registro = [
        'tipo' => 'cierre_trabajo',
        'uuid_cliente' => $uuidCliente,
        'trabajo_uuid_cliente' => $trabajoUuidCliente,
        'fin' => $fin,
    ];

    if ($evidenciaImagenCampoUuidCliente !== null) {
        $registro['evidencia_imagen_campo_uuid_cliente'] = $evidenciaImagenCampoUuidCliente;
    }

    return $registro;
}

/*
 * ── Caso 1: sin captura no cierra ──
 */

it('cerrar el lote SIN evidencia_imagen_campo_uuid_cliente se rechaza (sin captura no cierra)', function () {
    $piloto = pilotoParaCierreLote();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierreLote('uuid-lote-sin-ev'),
        registroSesionParaCierreLote('uuid-sesion-sin-ev', 'uuid-lote-sin-ev', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajoParaLote('uuid-cierre-sin-ev', 'uuid-lote-sin-ev', null),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado');
    expect(Trabajo::query()->where('uuid_cliente', 'uuid-lote-sin-ev')->firstOrFail()->estado)->toBe(EstadoTrabajo::Abierto);
});

/*
 * ── Caso 2: evidencia de tipo distinto de imagen_campo ──
 */

it('cerrar el lote con una evidencia de tipo distinto de imagen_campo se rechaza', function () {
    $piloto = pilotoParaCierreLote();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierreLote('uuid-lote-tipo-malo'),
        registroSesionParaCierreLote('uuid-sesion-tipo-malo', 'uuid-lote-tipo-malo', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajoParaLote('uuid-cierre-tipo-malo', 'uuid-lote-tipo-malo', evidenciaTipoDistintoParaLote('tipo-malo')),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull();
    expect(Trabajo::query()->where('uuid_cliente', 'uuid-lote-tipo-malo')->firstOrFail()->estado)->toBe(EstadoTrabajo::Abierto);
});

/*
 * ── Caso 3: evidencia inexistente ──
 */

it('cerrar el lote referenciando una evidencia inexistente se rechaza', function () {
    $piloto = pilotoParaCierreLote();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierreLote('uuid-lote-ev-fantasma'),
        registroSesionParaCierreLote('uuid-sesion-ev-fantasma', 'uuid-lote-ev-fantasma', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajoParaLote('uuid-cierre-ev-fantasma', 'uuid-lote-ev-fantasma', 'uuid-evidencia-que-no-existe'),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull();
    expect(Trabajo::query()->where('uuid_cliente', 'uuid-lote-ev-fantasma')->firstOrFail()->estado)->toBe(EstadoTrabajo::Abierto);
});

/*
 * ── Caso 4: cierre válido — se aplica y la referencia queda persistida ──
 */

it('cerrar el lote con evidencia imagen_campo válida se aplica y persiste la referencia', function () {
    $piloto = pilotoParaCierreLote();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierreLote('uuid-lote-ok'),
        registroSesionParaCierreLote('uuid-sesion-ok', 'uuid-lote-ok', $piloto->id),
    ]])->assertOk();

    $evidenciaUuid = evidenciaImagenCampoParaLote('ok');

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajoParaLote('uuid-cierre-ok', 'uuid-lote-ok', $evidenciaUuid),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('aplicado');

    $trabajo = Trabajo::query()->where('uuid_cliente', 'uuid-lote-ok')->firstOrFail();
    $evidencia = Evidencia::query()->where('uuid_cliente', $evidenciaUuid)->firstOrFail();

    expect($trabajo->estado)->toBe(EstadoTrabajo::Cerrado)
        ->and($trabajo->imagen_campo_evidencia_id)->toBe($evidencia->id);
});

/*
 * ── Caso adicional (hallazgo de la revisión crítica): la misma evidencia no
 *    puede cerrar dos trabajos distintos — "demostrable" exige que cada
 *    imagen del campo sea de UN lote ──
 */

it('cerrar un segundo trabajo con la MISMA evidencia ya usada por otro trabajo se rechaza', function () {
    $piloto = pilotoParaCierreLote();
    $evidenciaCompartida = evidenciaImagenCampoParaLote('reusada');

    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierreLote('uuid-lote-reuso-a'),
        registroSesionParaCierreLote('uuid-sesion-reuso-a', 'uuid-lote-reuso-a', $piloto->id),
    ]])->assertOk();
    $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajoParaLote('uuid-cierre-reuso-a', 'uuid-lote-reuso-a', $evidenciaCompartida),
    ]])->assertOk();

    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierreLote('uuid-lote-reuso-b'),
        registroSesionParaCierreLote('uuid-sesion-reuso-b', 'uuid-lote-reuso-b', $piloto->id),
    ]])->assertOk();

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajoParaLote('uuid-cierre-reuso-b', 'uuid-lote-reuso-b', $evidenciaCompartida),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('rechazado')
        ->and($respuesta->json('resultados.0.motivo'))->not->toBeNull();
    expect(Trabajo::query()->where('uuid_cliente', 'uuid-lote-reuso-a')->firstOrFail()->estado)->toBe(EstadoTrabajo::Cerrado)
        ->and(Trabajo::query()->where('uuid_cliente', 'uuid-lote-reuso-b')->firstOrFail()->estado)->toBe(EstadoTrabajo::Abierto);
});

/*
 * ── Caso 5: reintento idempotente ──
 */

it('reintentar el mismo cierre de lote con evidencia responde duplicado sin recerrar', function () {
    $piloto = pilotoParaCierreLote();
    $this->postJson('/api/sync', ['registros' => [
        registroTrabajoParaCierreLote('uuid-lote-idem'),
        registroSesionParaCierreLote('uuid-sesion-idem', 'uuid-lote-idem', $piloto->id),
    ]])->assertOk();

    $cierre = ['registros' => [
        registroCierreTrabajoParaLote('uuid-cierre-idem', 'uuid-lote-idem', evidenciaImagenCampoParaLote('idem')),
    ]];

    $this->postJson('/api/sync', $cierre)->assertOk();
    $segunda = $this->postJson('/api/sync', $cierre)->assertOk();

    expect($segunda->json('resultados.0.estado'))->toBe('duplicado')
        ->and(Trabajo::query()->where('uuid_cliente', 'uuid-lote-idem')->count())->toBe(1);
});

/*
 * ── Regresión: el cierre no "limpia" el cálculo de observado (tarea 20) ──
 */

it('un trabajo observado por exceder la tolerancia sigue observado después de cerrarse con evidencia válida', function () {
    config(['operaciones.tolerancia_solape_hectareas' => '2.00']);

    $cliente = Cliente::create(['razon_social' => 'Cliente cobertura cierre', 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo cobertura cierre']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-COB-CIERRE', 'hectareas' => '10.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '10.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '100.00',
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

    $piloto = pilotoParaCierreLote();

    $this->postJson('/api/sync', ['registros' => [
        [
            'tipo' => 'trabajo',
            'uuid_cliente' => 'uuid-trabajo-observado',
            'orden_id' => $orden->id,
            'lote_id' => $lote->id,
            'nro_aplicacion' => 1,
            'inicio' => '2026-09-01T08:00:00-04:00',
        ],
        [
            'tipo' => 'sesion',
            'uuid_cliente' => 'uuid-sesion-observado',
            'trabajo_uuid_cliente' => 'uuid-trabajo-observado',
            'secuencia' => 1,
            'piloto_id' => $piloto->id,
            'inicio' => '2026-09-01T08:05:00-04:00',
        ],
    ]])->assertOk();

    // Lote de 10.00 ha + tolerancia 2.00 = límite 12.00; 12.01 lo excede.
    $this->postJson('/api/sync', ['registros' => [
        [
            'tipo' => 'cierre_sesion',
            'uuid_cliente' => 'uuid-cierre-sesion-observado',
            'sesion_uuid_cliente' => 'uuid-sesion-observado',
            'fin' => '2026-09-01T09:00:00-04:00',
            'motivo_cierre' => 'completado',
            'hectareas_declaradas' => '12.01',
        ],
    ]])->assertOk();

    $trabajo = Trabajo::query()->where('uuid_cliente', 'uuid-trabajo-observado')->firstOrFail();
    expect(app(CalcularCoberturaTrabajo::class)->ejecutar($trabajo))->toBe(EstadoCoberturaTrabajo::Observado);

    $respuesta = $this->postJson('/api/sync', ['registros' => [
        registroCierreTrabajoParaLote('uuid-cierre-trabajo-observado', 'uuid-trabajo-observado', evidenciaImagenCampoParaLote('observado')),
    ]])->assertOk();

    expect($respuesta->json('resultados.0.estado'))->toBe('aplicado');

    $trabajoCerrado = $trabajo->fresh();
    expect($trabajoCerrado->estado)->toBe(EstadoTrabajo::Cerrado)
        ->and(app(CalcularCoberturaTrabajo::class)->ejecutar($trabajoCerrado))->toBe(EstadoCoberturaTrabajo::Observado);
});
