<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\ReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/*
 * HU-43 (tarea 57): "como encargado, quiero listar y descargar los reportes
 * técnicos generados, para reenviarlos al agrónomo" (`plan_sprints.md`
 * Sprint 12, §252) — cierra Sprint 12. Solo lectura, filtrable por cliente y
 * por período de generación (`generado_en`). Reusa `operaciones.reporte.ver`
 * (mismo permiso que la descarga individual, `panel.trabajos.reporte-pdf`).
 *
 * `ReporteTecnico` se crea directo por Eloquent (mismo criterio que
 * `ReporteTecnicoTest`): esta pantalla no ejercita la generación automática
 * del reporte al firmar el acta, solo su listado.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Storage::fake('r2');
});

function ordenParaListadoReportes(string $sufijo, ?Cliente $cliente = null): OrdenAplicacion
{
    $cliente ??= Cliente::create(['razon_social' => "Cliente listado reportes {$sufijo}", 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => "Campo listado reportes {$sufijo}"]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => "L-LISTA-{$sufijo}", 'hectareas' => '20.00']);
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
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);

    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => '20.00']);

    return $orden;
}

/** Trabajo + reporte técnico ya generado (fixture directa por Eloquent), con su cliente. */
function reporteParaListado(string $sufijo, string $generadoEn, ?Cliente $cliente = null): array
{
    $orden = ordenParaListadoReportes($sufijo, $cliente);

    $trabajo = Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-listado-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => (int) $orden->ordenLotes()->value('lote_id'),
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '20.00',
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => '2026-09-01T08:00:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
    ]);

    $pdfPath = "reportes_tecnicos/2026/09/uuid-listado-{$sufijo}.pdf";
    Storage::disk('r2')->put($pdfPath, "contenido-{$sufijo}");

    $reporte = ReporteTecnico::create([
        'trabajo_id' => $trabajo->id,
        'hora_inicio' => '2026-09-01T08:05:00-04:00',
        'hora_fin' => '2026-09-01T11:00:00-04:00',
        'pdf_path' => $pdfPath,
        'generado_en' => $generadoEn,
    ]);

    $orden->refresh();

    return ['trabajo' => $trabajo, 'reporte' => $reporte, 'contrato' => Contrato::query()->findOrFail($orden->contrato_id)];
}

function usuarioConRolParaListadoReportes(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaListadoReportes(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function jefeCampoEntraAlPanelParaListadoReportes(): SecUser
{
    [$jefe, $idRol] = usuarioConRolParaListadoReportes('jefe.listado-reportes.'.uniqid(), 'jefe_campo');
    entrarAlPanelParaListadoReportes($jefe, $idRol);

    return $jefe;
}

it('lista todos los reportes técnicos generados, sin filtro', function () {
    $a = reporteParaListado('sinfiltro-a', '2026-09-01T10:00:00-04:00');
    $b = reporteParaListado('sinfiltro-b', '2026-09-02T10:00:00-04:00');

    jefeCampoEntraAlPanelParaListadoReportes();

    $this->get(route('panel.reportes.tecnicos.index'))
        ->assertOk()
        ->assertSee($a['contrato']->cliente->razon_social)
        ->assertSee($b['contrato']->cliente->razon_social);
});

it('filtra por cliente_id: solo trae los reportes de contratos de ese cliente', function () {
    $clienteA = Cliente::create(['razon_social' => 'Cliente listado filtro A', 'tipo_persona' => 'juridica']);
    $clienteB = Cliente::create(['razon_social' => 'Cliente listado filtro B', 'tipo_persona' => 'juridica']);

    reporteParaListado('filtro-a', '2026-09-01T10:00:00-04:00', $clienteA);
    reporteParaListado('filtro-b', '2026-09-01T10:00:00-04:00', $clienteB);

    jefeCampoEntraAlPanelParaListadoReportes();

    // El <select> de cliente siempre lista TODOS los clientes disponibles
    // (mismo criterio que ReportesComercialesController), así que el nombre
    // del cliente excluido igual aparece como OPCIÓN — se verifica contra el
    // código de lote, que solo se pinta en las filas de la tabla.
    $this->get(route('panel.reportes.tecnicos.index', ['cliente_id' => $clienteA->id]))
        ->assertOk()
        ->assertSee('L-LISTA-filtro-a')
        ->assertDontSee('L-LISTA-filtro-b');
});

it('filtra por período: desde/hasta sobre generado_en', function () {
    reporteParaListado('periodo-viejo', '2026-08-01T10:00:00-04:00');
    reporteParaListado('periodo-en-rango', '2026-09-05T10:00:00-04:00');
    reporteParaListado('periodo-nuevo', '2026-10-01T10:00:00-04:00');

    jefeCampoEntraAlPanelParaListadoReportes();

    $this->get(route('panel.reportes.tecnicos.index', ['desde' => '2026-09-01', 'hasta' => '2026-09-30']))
        ->assertOk()
        ->assertSee('L-LISTA-periodo-en-rango')
        ->assertDontSee('L-LISTA-periodo-viejo')
        ->assertDontSee('L-LISTA-periodo-nuevo');
});

it('un rol sin operaciones.reporte.ver recibe 403', function () {
    [$piloto, $idRol] = usuarioConRolParaListadoReportes('piloto.listado-reportes', 'piloto');
    entrarAlPanelParaListadoReportes($piloto, $idRol);

    $this->get(route('panel.reportes.tecnicos.index'))->assertForbidden();
});

it('el link de descarga de cada fila resuelve al PDF real ya existente', function () {
    $fixture = reporteParaListado('descarga', '2026-09-01T10:00:00-04:00');

    jefeCampoEntraAlPanelParaListadoReportes();

    $this->get(route('panel.reportes.tecnicos.index'))
        ->assertOk()
        ->assertSee(route('panel.trabajos.reporte-pdf', $fixture['trabajo']->id), false);

    $this->get(route('panel.trabajos.reporte-pdf', $fixture['trabajo']->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('publica el ítem de menú de reportes técnicos gateado por operaciones.reporte.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.reportes.items.tecnicos')->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'operaciones.reporte.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.reportes.tecnicos.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
