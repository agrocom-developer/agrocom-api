<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/*
 * `POST /api/trabajos/{uuid_cliente}/acta`, `POST /api/actas/{uuid_cliente}/firmar`
 * (HU-17, tarea 24): acta de conformidad por lote, con firma del agrónomo
 * referenciando una evidencia ya subida (`TipoEvidencia::FirmaActa`).
 *
 * Los fixtures crean `Trabajo`/`Sesion` directo por Eloquent (mismo criterio
 * que `ValidarSesionTest.php`), no vía `/api/sync`: lo que este archivo
 * prueba es el acta, no el motor de sync.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Storage::fake('r2');
});

/** Usuario con el rol `piloto` (HU-17: `operaciones.acta.generar`/`.firmar` sembrados por `SeguridadSeeder`). */
function usuarioConPermisoActa(): SecUser
{
    $usuario = SecUser::factory()->create();
    $idRolPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRolPiloto]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $usuario;
}

/** Usuario autenticado sin ningún rol asignado — ninguna acción de acta le corresponde. */
function usuarioSinPermisoActa(): SecUser
{
    return SecUser::factory()->create();
}

function ordenParaActa(string $sufijo): OrdenAplicacion
{
    $cliente = Cliente::create(['razon_social' => "Cliente acta {$sufijo}", 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => "Campo acta {$sufijo}"]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => "L-ACTA-{$sufijo}", 'hectareas' => '20.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '20.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '200.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);

    return OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
}

function trabajoParaActa(string $sufijo, EstadoTrabajo $estado): Trabajo
{
    $orden = ordenParaActa($sufijo);

    return Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-acta-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '18.50',
        'estado' => $estado,
        'inicio' => '2026-09-01T08:00:00-04:00',
        'fin' => $estado === EstadoTrabajo::Cerrado ? '2026-09-01T12:00:00-04:00' : null,
    ]);
}

function sesionParaActa(Trabajo $trabajo, string $sufijo, EstadoSesion $estado): Sesion
{
    $piloto = PerPersona::create(['nombre' => "Piloto acta {$sufijo}", 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    return Sesion::create([
        'uuid_cliente' => "uuid-sesion-acta-{$sufijo}",
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '18.50',
        'estado' => $estado,
        'inicio' => '2026-09-01T08:05:00-04:00',
        'fin' => '2026-09-01T11:00:00-04:00',
        'motivo_cierre' => 'completado',
    ]);
}

/** Trabajo `cerrado` con su única sesión vigente `validado` — listo para generar el acta. */
function trabajoListoParaActa(string $sufijo): Trabajo
{
    $trabajo = trabajoParaActa($sufijo, EstadoTrabajo::Cerrado);
    sesionParaActa($trabajo, $sufijo, EstadoSesion::Validado);

    return $trabajo;
}

/** @return string uuid_cliente de la evidencia creada */
function evidenciaFirmaActa(string $sufijo): string
{
    $uuidCliente = "uuid-firma-acta-{$sufijo}";

    Evidencia::create([
        'uuid_cliente' => $uuidCliente,
        'tipo' => TipoEvidencia::FirmaActa,
        'archivo_url' => "evidencias/firma_acta/2026/09/{$uuidCliente}.jpg",
        'hash' => hash('sha256', $uuidCliente),
        'fecha' => '2026-09-02T10:00:00-04:00',
    ]);

    return $uuidCliente;
}

/** @return string uuid_cliente de una evidencia de tipo DISTINTO de firma_acta */
function evidenciaTipoDistintoParaActa(string $sufijo): string
{
    $uuidCliente = "uuid-firma-acta-tipo-malo-{$sufijo}";

    Evidencia::create([
        'uuid_cliente' => $uuidCliente,
        'tipo' => TipoEvidencia::ImagenCampo,
        'archivo_url' => "evidencias/imagen_campo/2026/09/{$uuidCliente}.jpg",
        'hash' => hash('sha256', $uuidCliente),
        'fecha' => '2026-09-02T10:00:00-04:00',
    ]);

    return $uuidCliente;
}

/*
 * ── Caso 1: generar acta sobre trabajo abierto → rechazado ──
 */

it('generar el acta de un trabajo abierto se rechaza', function () {
    $trabajo = trabajoParaActa('abierto', EstadoTrabajo::Abierto);

    $respuesta = $this->actingAs(usuarioConPermisoActa(), 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-abierto'])
        ->assertOk();

    expect($respuesta->json('estado'))->toBe('rechazado')
        ->and($respuesta->json('motivo'))->not->toBeNull();
    expect(Acta::query()->count())->toBe(0);
});

/*
 * ── Guarda propia de esta tarea: sesiones vigentes sin validar → rechazado
 *    (ver runs/24.md, "¿exigir sesiones validadas?") ──
 */

it('generar el acta de un trabajo cerrado con sesiones vigentes sin validar se rechaza', function () {
    $trabajo = trabajoParaActa('sin-validar', EstadoTrabajo::Cerrado);
    sesionParaActa($trabajo, 'sin-validar', EstadoSesion::Cerrado);

    $respuesta = $this->actingAs(usuarioConPermisoActa(), 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-sin-validar'])
        ->assertOk();

    expect($respuesta->json('estado'))->toBe('rechazado');
    expect(Acta::query()->count())->toBe(0);
});

/*
 * ── Caso 2: generar acta sobre trabajo cerrado y validado → creada, con snapshot ──
 */

it('generar el acta de un trabajo cerrado y validado la crea con el snapshot de hectáreas declaradas', function () {
    $trabajo = trabajoListoParaActa('ok');

    $respuesta = $this->actingAs(usuarioConPermisoActa(), 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-ok'])
        ->assertOk();

    expect($respuesta->json('estado'))->toBe('pendiente')
        ->and($respuesta->json('hectareas_conformadas'))->toBe('18.50');

    $acta = Acta::query()->where('trabajo_id', $trabajo->id)->firstOrFail();
    expect($acta->uuid_cliente)->toBe('uuid-acta-ok')
        ->and((string) $acta->hectareas_conformadas)->toBe('18.50')
        ->and($acta->pdf_path)->not->toBeNull();

    Storage::disk('r2')->assertExists($acta->pdf_path);
});

/*
 * ── Caso 3: generar dos veces sobre el mismo trabajo → misma fila, no regenera ──
 */

it('generar el acta dos veces sobre el mismo trabajo devuelve la misma fila sin regenerar el PDF', function () {
    $trabajo = trabajoListoParaActa('doble');
    $usuario = usuarioConPermisoActa();

    $primera = $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-doble-1'])
        ->assertOk();

    $acta = Acta::query()->where('trabajo_id', $trabajo->id)->firstOrFail();
    Storage::disk('r2')->put($acta->pdf_path, 'contenido-original-no-tocar');

    $segunda = $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-doble-2'])
        ->assertOk();

    expect($primera->json('id'))->toBe($segunda->json('id'))
        ->and($segunda->json('uuid_cliente'))->toBe('uuid-acta-doble-1');
    expect(Acta::query()->where('trabajo_id', $trabajo->id)->count())->toBe(1);
    expect(Storage::disk('r2')->get($acta->pdf_path))->toBe('contenido-original-no-tocar');
});

/*
 * ── Hallazgo de la revisión crítica: uuid_cliente de acta reutilizado entre
 *    DOS trabajos distintos choca contra el índice único real (no una
 *    condición de carrera simulable en un solo proceso, pero el mismo
 *    `QueryException` que dispara — ver GenerarActaTrabajo::ejecutar()) →
 *    rechazado, nunca un 500 ──
 */

it('generar el acta con un uuid_cliente ya usado por el acta de OTRO trabajo se rechaza', function () {
    $trabajoA = trabajoListoParaActa('conflicto-a');
    $trabajoB = trabajoListoParaActa('conflicto-b');
    $usuario = usuarioConPermisoActa();

    $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajoA->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-conflicto-compartido'])
        ->assertOk();

    $respuesta = $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajoB->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-conflicto-compartido'])
        ->assertOk();

    expect($respuesta->json('estado'))->toBe('rechazado');
    expect(Acta::query()->where('trabajo_id', $trabajoB->id)->count())->toBe(0);
});

/*
 * ── Autorización: sin el permiso operaciones.acta.generar → 403 ──
 */

it('generar el acta sin el permiso operaciones.acta.generar responde 403', function () {
    $trabajo = trabajoListoParaActa('sinpermiso');

    $this->actingAs(usuarioSinPermisoActa(), 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-sinpermiso'])
        ->assertForbidden();

    expect(Acta::query()->count())->toBe(0);
});

/*
 * ── Caso 4: firmar con evidencia inexistente → rechazado ──
 */

it('firmar con una evidencia inexistente se rechaza', function () {
    $trabajo = trabajoListoParaActa('firma-fantasma');
    $usuario = usuarioConPermisoActa();

    $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-firma-fantasma'])
        ->assertOk();

    $acta = Acta::query()->where('trabajo_id', $trabajo->id)->firstOrFail();

    $respuesta = $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/actas/{$acta->uuid_cliente}/firmar", [
            'evidencia_firma_uuid_cliente' => 'uuid-evidencia-que-no-existe',
            'firmante' => 'Ing. Agrónoma de Prueba',
            'fecha_firma' => '2026-09-02T16:00:00-04:00',
        ])->assertOk();

    expect($respuesta->json('estado'))->toBe('rechazado');
    expect($acta->fresh()->estado->value)->toBe('pendiente');
});

/*
 * ── Caso 5: firmar con evidencia de tipo distinto de firma_acta → rechazado ──
 */

it('firmar con una evidencia de tipo distinto de firma_acta se rechaza', function () {
    $trabajo = trabajoListoParaActa('tipo-malo');
    $usuario = usuarioConPermisoActa();

    $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-tipo-malo'])
        ->assertOk();

    $acta = Acta::query()->where('trabajo_id', $trabajo->id)->firstOrFail();
    $evidenciaTipoMalo = evidenciaTipoDistintoParaActa('tipo-malo');

    $respuesta = $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/actas/{$acta->uuid_cliente}/firmar", [
            'evidencia_firma_uuid_cliente' => $evidenciaTipoMalo,
            'firmante' => 'Ing. Agrónoma de Prueba',
            'fecha_firma' => '2026-09-02T16:00:00-04:00',
        ])->assertOk();

    expect($respuesta->json('estado'))->toBe('rechazado');
    expect($acta->fresh()->estado->value)->toBe('pendiente');
});

/*
 * ── Caso 6: firmar válido → firmada, con firmante/fecha_firma/evidencia persistidos ──
 */

it('firmar con una evidencia firma_acta válida deja el acta firmada con los datos correctos', function () {
    $trabajo = trabajoListoParaActa('firma-ok');
    $usuario = usuarioConPermisoActa();

    $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-firma-ok'])
        ->assertOk();

    $acta = Acta::query()->where('trabajo_id', $trabajo->id)->firstOrFail();
    $evidenciaUuid = evidenciaFirmaActa('firma-ok');

    $respuesta = $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/actas/{$acta->uuid_cliente}/firmar", [
            'evidencia_firma_uuid_cliente' => $evidenciaUuid,
            'firmante' => 'Ing. Marcela Vargas',
            'fecha_firma' => '2026-09-02T16:30:00-04:00',
        ])->assertOk();

    expect($respuesta->json('estado'))->toBe('firmada')
        ->and($respuesta->json('firmante'))->toBe('Ing. Marcela Vargas');

    $actaFirmada = $acta->fresh();
    $evidencia = Evidencia::query()->where('uuid_cliente', $evidenciaUuid)->firstOrFail();

    expect($actaFirmada->estado->value)->toBe('firmada')
        ->and($actaFirmada->firmante)->toBe('Ing. Marcela Vargas')
        ->and($actaFirmada->evidencia_firma_id)->toBe($evidencia->id)
        // El instante real (no la hora local literal) — ver el porqué del
        // `->utc()` en `MaquinaEstadosActa::firmar()`.
        ->and($actaFirmada->fecha_firma?->timestamp)->toBe(strtotime('2026-09-02T16:30:00-04:00'));
});

/*
 * ── Caso 7: reintento de firma sobre acta ya firmada, mismo evento → idempotente ──
 */

it('reintentar la firma con la misma evidencia sobre un acta ya firmada es idempotente', function () {
    $trabajo = trabajoListoParaActa('firma-idem');
    $usuario = usuarioConPermisoActa();

    $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-firma-idem'])
        ->assertOk();

    $acta = Acta::query()->where('trabajo_id', $trabajo->id)->firstOrFail();
    $evidenciaUuid = evidenciaFirmaActa('firma-idem');

    $payload = [
        'evidencia_firma_uuid_cliente' => $evidenciaUuid,
        'firmante' => 'Ing. Marcela Vargas',
        'fecha_firma' => '2026-09-02T16:30:00-04:00',
    ];

    $this->actingAs($usuario, 'sanctum')->postJson("/api/actas/{$acta->uuid_cliente}/firmar", $payload)->assertOk();
    $actaTrasPrimeraFirma = $acta->fresh();

    $segunda = $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/actas/{$acta->uuid_cliente}/firmar", $payload)
        ->assertOk();

    expect($segunda->json('estado'))->toBe('firmada');

    $actaTrasReintento = $acta->fresh();
    expect($actaTrasReintento->updated_at)->toEqual($actaTrasPrimeraFirma->updated_at)
        ->and($actaTrasReintento->firmante)->toBe($actaTrasPrimeraFirma->firmante)
        ->and($actaTrasReintento->fecha_firma)->toEqual($actaTrasPrimeraFirma->fecha_firma);
});

/*
 * ── Reintento con OTRA evidencia sobre un acta ya firmada → rechazado (conflicto) ──
 */

it('firmar con OTRA evidencia un acta ya firmada se rechaza', function () {
    $trabajo = trabajoListoParaActa('firma-conflicto');
    $usuario = usuarioConPermisoActa();

    $this->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => 'uuid-acta-firma-conflicto'])
        ->assertOk();

    $acta = Acta::query()->where('trabajo_id', $trabajo->id)->firstOrFail();

    $this->actingAs($usuario, 'sanctum')->postJson("/api/actas/{$acta->uuid_cliente}/firmar", [
        'evidencia_firma_uuid_cliente' => evidenciaFirmaActa('firma-conflicto-1'),
        'firmante' => 'Ing. Marcela Vargas',
        'fecha_firma' => '2026-09-02T16:30:00-04:00',
    ])->assertOk();

    $respuesta = $this->actingAs($usuario, 'sanctum')->postJson("/api/actas/{$acta->uuid_cliente}/firmar", [
        'evidencia_firma_uuid_cliente' => evidenciaFirmaActa('firma-conflicto-2'),
        'firmante' => 'Otro Firmante',
        'fecha_firma' => '2026-09-02T17:00:00-04:00',
    ])->assertOk();

    expect($respuesta->json('estado'))->toBe('rechazado');
    expect($acta->fresh()->firmante)->toBe('Ing. Marcela Vargas');
});

/*
 * ── Caso 8: firmar un acta inexistente → 404 ──
 */

it('firmar un acta inexistente responde 404', function () {
    $this->actingAs(usuarioConPermisoActa(), 'sanctum')
        ->postJson('/api/actas/uuid-acta-que-no-existe/firmar', [
            'evidencia_firma_uuid_cliente' => evidenciaFirmaActa('acta-fantasma'),
            'firmante' => 'Ing. Agrónoma de Prueba',
            'fecha_firma' => '2026-09-02T16:00:00-04:00',
        ])
        ->assertNotFound();
});

/*
 * ── Generar el acta de un trabajo inexistente → 404 (route-model-binding) ──
 */

it('generar el acta de un trabajo inexistente responde 404', function () {
    $this->actingAs(usuarioConPermisoActa(), 'sanctum')
        ->postJson('/api/trabajos/uuid-trabajo-que-no-existe/acta', ['uuid_cliente' => 'uuid-acta-trabajo-fantasma'])
        ->assertNotFound();
});
