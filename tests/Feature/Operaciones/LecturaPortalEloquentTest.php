<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Contratos\LecturaActaConformada;
use App\Dominios\Operaciones\Contratos\LecturaReporteTecnico;
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
 * HU-41 (tarea 55), etapa 2: `LecturaActaConformada::listarFirmadasPorContrato()`
 * y `LecturaReporteTecnico::listarPorContrato()` — ambos resuelven la cadena
 * Acta/ReporteTecnico → Trabajo → OrdenAplicacion → contrato_id, y ambos
 * deben devolver SOLO lo del contrato pedido, nunca "todo con un where
 * después" (invariante 5 de CLAUDE.md, aplicada acá aunque el consumidor sea
 * interno del servidor — es el molde que `Portal` reusará en la etapa 3).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Storage::fake('r2');
});

function contratoParaLecturaPortal(string $sufijo): Contrato
{
    $cliente = Cliente::create(['razon_social' => "Cliente lectura portal {$sufijo}", 'tipo_persona' => 'juridica']);

    return Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '50.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '0.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);
}

function ordenParaLecturaPortal(Contrato $contrato, string $sufijo): OrdenAplicacion
{
    $propiedad = Propiedad::create(['cliente_id' => $contrato->cliente_id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $lote = Lote::create(['propiedad_id' => $propiedad->id, 'codigo' => "L-PORTAL-{$sufijo}", 'hectareas' => '25.00']);

    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);

    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => '25.00']);

    return $orden;
}

/** HU-92 (tarea 107): la orden ya no tiene `lote_id` propio; se resuelve vía `ordenLotes()`. */
function loteIdParaLecturaPortal(OrdenAplicacion $orden): int
{
    return (int) $orden->ordenLotes()->value('lote_id');
}

function usuarioPilotoParaLecturaPortal(string $sufijo): SecUser
{
    $usuario = SecUser::factory()->create(['username' => "piloto.lectura.{$sufijo}"]);
    $idRolPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRolPiloto]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $usuario;
}

/** Acta FIRMADA (y su ReporteTecnico generado al firmar) de un trabajo nuevo de esa orden. */
function actaFirmadaParaLecturaPortal(OrdenAplicacion $orden, string $sufijo): Acta
{
    $trabajo = Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-lectura-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => loteIdParaLecturaPortal($orden),
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '5.00',
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => '2026-09-01T08:00:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
    ]);

    $piloto = PerPersona::create(['nombre' => "Piloto lectura {$sufijo}", 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    Sesion::create([
        'uuid_cliente' => "uuid-sesion-lectura-{$sufijo}",
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '5.00',
        'estado' => EstadoSesion::Validado,
        'inicio' => '2026-09-01T08:05:00-04:00',
        'fin' => '2026-09-01T11:00:00-04:00',
        'motivo_cierre' => 'completado',
    ]);

    $usuario = usuarioPilotoParaLecturaPortal($sufijo);

    test()->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => "uuid-acta-lectura-{$sufijo}"])
        ->assertOk();

    $acta = Acta::query()->where('trabajo_id', $trabajo->id)->firstOrFail();

    $evidenciaUuid = "uuid-firma-lectura-{$sufijo}";
    Evidencia::create([
        'uuid_cliente' => $evidenciaUuid,
        'tipo' => TipoEvidencia::FirmaActa,
        'archivo_url' => "evidencias/firma_acta/2026/09/{$evidenciaUuid}.jpg",
        'hash' => hash('sha256', $evidenciaUuid),
        'fecha' => '2026-09-02T10:00:00-04:00',
    ]);

    test()->actingAs($usuario, 'sanctum')
        ->postJson("/api/actas/{$acta->uuid_cliente}/firmar", [
            'evidencia_firma_uuid_cliente' => $evidenciaUuid,
            'firmante' => 'Ing. Agrónoma de Prueba',
            'fecha_firma' => '2026-09-02T16:00:00-04:00',
        ])
        ->assertOk();

    return $acta->fresh();
}

it('listarFirmadasPorContrato devuelve solo las actas firmadas del contrato pedido', function () {
    $contratoA = contratoParaLecturaPortal('actas-a');
    $contratoB = contratoParaLecturaPortal('actas-b');

    $actaA = actaFirmadaParaLecturaPortal(ordenParaLecturaPortal($contratoA, 'actas-a'), 'actas-a');
    actaFirmadaParaLecturaPortal(ordenParaLecturaPortal($contratoB, 'actas-b'), 'actas-b');

    $resultado = app(LecturaActaConformada::class)->listarFirmadasPorContrato($contratoA->id);

    expect($resultado)->toHaveCount(1)
        ->and($resultado[0]->actaId)->toBe($actaA->id)
        ->and($resultado[0]->contratoId)->toBe($contratoA->id)
        ->and($resultado[0]->firmada)->toBeTrue();
});

it('listarFirmadasPorContrato devuelve vacío para un contrato sin actas', function () {
    $contrato = contratoParaLecturaPortal('actas-vacio');

    expect(app(LecturaActaConformada::class)->listarFirmadasPorContrato($contrato->id))->toBe([]);
});

it('listarPorContrato de reportes técnicos devuelve solo los del contrato pedido', function () {
    $contratoA = contratoParaLecturaPortal('reportes-a');
    $contratoB = contratoParaLecturaPortal('reportes-b');

    $ordenA = ordenParaLecturaPortal($contratoA, 'reportes-a');
    $actaA = actaFirmadaParaLecturaPortal($ordenA, 'reportes-a');
    actaFirmadaParaLecturaPortal(ordenParaLecturaPortal($contratoB, 'reportes-b'), 'reportes-b');

    $resultado = app(LecturaReporteTecnico::class)->listarPorContrato($contratoA->id);

    expect($resultado)->toHaveCount(1)
        ->and($resultado[0]->trabajoId)->toBe($actaA->trabajo_id)
        ->and($resultado[0]->contratoId)->toBe($contratoA->id)
        ->and($resultado[0]->loteId)->toBe(loteIdParaLecturaPortal($ordenA))
        ->and($resultado[0]->pdfPath)->not->toBeNull();
});

it('listarPorContrato de reportes técnicos devuelve vacío para un contrato sin reportes', function () {
    $contrato = contratoParaLecturaPortal('reportes-vacio');

    expect(app(LecturaReporteTecnico::class)->listarPorContrato($contrato->id))->toBe([]);
});
