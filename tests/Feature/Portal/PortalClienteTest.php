<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
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
use App\Dominios\Operaciones\Infraestructura\Eloquent\ReporteTecnico;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioCliente;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/*
 * HU-41 (tarea 55), etapa 3: pantallas del portal (avance, actas, reportes) y
 * sus descargas de PDF. Criterio de aceptación literal de la HU: para CADA
 * endpoint nuevo, cliente A pidiendo un recurso de cliente B → 404
 * (invariante 5 de CLAUDE.md) — nunca 403 (eso revelaría que el recurso
 * existe).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Storage::fake('r2');
});

function contratoParaPortal(string $sufijo, string $hectareas = '50.00', string $precioHa = '10.00'): Contrato
{
    $cliente = Cliente::create(['razon_social' => "Cliente portal {$sufijo}", 'tipo_persona' => 'juridica']);

    return Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => $hectareas,
        'aplicaciones_previstas' => 1,
        'precio_ha' => $precioHa,
        'monto_total' => '0.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);
}

function ordenParaPortal(Contrato $contrato, string $sufijo): OrdenAplicacion
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

/** Id del único lote de `$orden` (HU-92: el lote ya no es columna de la orden). */
function loteIdParaPortal(OrdenAplicacion $orden): int
{
    return (int) $orden->ordenLotes()->value('lote_id');
}

/** Acta FIRMADA (con su ReporteTecnico generado al firmar) de un trabajo nuevo de esa orden. */
function actaFirmadaParaPortal(OrdenAplicacion $orden, string $sufijo): Acta
{
    $trabajo = Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-portal-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => loteIdParaPortal($orden),
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '5.00',
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => '2026-09-01T08:00:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
    ]);

    $piloto = PerPersona::create(['nombre' => "Piloto portal {$sufijo}", 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    Sesion::create([
        'uuid_cliente' => "uuid-sesion-portal-{$sufijo}",
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '5.00',
        'estado' => EstadoSesion::Validado,
        'inicio' => '2026-09-01T08:05:00-04:00',
        'fin' => '2026-09-01T11:00:00-04:00',
        'motivo_cierre' => 'completado',
    ]);

    $usuario = SecUser::factory()->create(['username' => "piloto.portal.{$sufijo}"]);
    $idRolPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRolPiloto]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    test()->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => "uuid-acta-portal-{$sufijo}"])
        ->assertOk();

    $acta = Acta::query()->where('trabajo_id', $trabajo->id)->firstOrFail();

    $evidenciaUuid = "uuid-firma-portal-{$sufijo}";
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

function cuentaPortalParaContrato(Contrato $contrato, string $username): SecUser
{
    return SecUser::factory()->create([
        'username' => $username,
        'password' => 'Secreta123',
        'type' => TipoUsuario::Cliente,
        'contrato_id' => $contrato->id,
    ]);
}

function entrarAlPortal(SecUser $usuario): void
{
    test()->actingAs(SecUsuarioCliente::query()->findOrFail($usuario->id), 'cliente');
}

// --- Avance -----------------------------------------------------------

it('el avance muestra las tres magnitudes del contrato propio', function () {
    $contrato = contratoParaPortal('avance', '40.00', '10.00');
    $orden = ordenParaPortal($contrato, 'avance');
    actaFirmadaParaPortal($orden, 'avance');

    $usuario = cuentaPortalParaContrato($contrato, 'cliente.avance');
    entrarAlPortal($usuario);

    $this->get(route('portal.avance.index'))
        ->assertOk()
        ->assertSee('40,00 ha')
        ->assertSee('5,00 ha');
});

it('el avance no requiere id en la URL: siempre es el del contrato de la sesión', function () {
    $contratoA = contratoParaPortal('avance-a', '10.00', '5.00');
    $contratoB = contratoParaPortal('avance-b', '99.00', '5.00');

    $usuarioA = cuentaPortalParaContrato($contratoA, 'cliente.avance.a');
    entrarAlPortal($usuarioA);

    $this->get(route('portal.avance.index'))
        ->assertOk()
        ->assertSee('10,00 ha')
        ->assertDontSee('99,00 ha');
});

it('el avance exige sesión de portal (guest)', function () {
    $this->get(route('portal.avance.index'))->assertRedirect(route('portal.login.form'));
});

it('una sesión del panel interno no puede ver el avance del portal', function () {
    $usuario = SecUser::factory()->create(['type' => TipoUsuario::Interno]);

    $this->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->get(route('portal.avance.index'))
        ->assertRedirect(route('portal.login.form'));
});

// --- Actas --------------------------------------------------------------

it('el listado de actas muestra solo las firmadas del contrato propio', function () {
    $contratoA = contratoParaPortal('actas-a');
    $contratoB = contratoParaPortal('actas-b');

    actaFirmadaParaPortal(ordenParaPortal($contratoA, 'actas-a'), 'actas-a');
    actaFirmadaParaPortal(ordenParaPortal($contratoB, 'actas-b'), 'actas-b');

    $usuarioA = cuentaPortalParaContrato($contratoA, 'cliente.actas.a');
    entrarAlPortal($usuarioA);

    $this->get(route('portal.actas.index'))
        ->assertOk()
        ->assertSee(__('portal.actas.acta_valor', ['id' => Acta::query()->where('trabajo_id', Trabajo::query()->where('uuid_cliente', 'uuid-trabajo-actas-a')->value('id'))->value('id')]));
});

it('descarga el PDF de la propia acta firmada', function () {
    $contrato = contratoParaPortal('acta-pdf');
    $acta = actaFirmadaParaPortal(ordenParaPortal($contrato, 'acta-pdf'), 'acta-pdf');

    $usuario = cuentaPortalParaContrato($contrato, 'cliente.acta.pdf');
    entrarAlPortal($usuario);

    $respuesta = $this->get(route('portal.actas.pdf', $acta->id));

    $respuesta->assertOk();
    expect($respuesta->headers->get('Content-Type'))->toContain('application/pdf');
});

it('cliente A pidiendo el acta de cliente B recibe 404', function () {
    $contratoA = contratoParaPortal('acta-cruzada-a');
    $contratoB = contratoParaPortal('acta-cruzada-b');

    actaFirmadaParaPortal(ordenParaPortal($contratoA, 'acta-cruzada-a'), 'acta-cruzada-a');
    $actaB = actaFirmadaParaPortal(ordenParaPortal($contratoB, 'acta-cruzada-b'), 'acta-cruzada-b');

    $usuarioA = cuentaPortalParaContrato($contratoA, 'cliente.acta.cruzada.a');
    entrarAlPortal($usuarioA);

    $this->get(route('portal.actas.pdf', $actaB->id))->assertNotFound();
});

it('un id de acta inexistente recibe 404', function () {
    $contrato = contratoParaPortal('acta-inexistente');
    $usuario = cuentaPortalParaContrato($contrato, 'cliente.acta.inexistente');
    entrarAlPortal($usuario);

    $this->get(route('portal.actas.pdf', 999999))->assertNotFound();
});

// --- Reportes técnicos ----------------------------------------------------

it('el listado de reportes muestra solo los del contrato propio', function () {
    $contratoA = contratoParaPortal('reportes-a');
    $contratoB = contratoParaPortal('reportes-b');

    $ordenA = ordenParaPortal($contratoA, 'reportes-a');
    actaFirmadaParaPortal($ordenA, 'reportes-a');
    actaFirmadaParaPortal(ordenParaPortal($contratoB, 'reportes-b'), 'reportes-b');

    $usuarioA = cuentaPortalParaContrato($contratoA, 'cliente.reportes.a');
    entrarAlPortal($usuarioA);

    $this->get(route('portal.reportes.index'))
        ->assertOk()
        ->assertSee(__('portal.reportes.lote_valor', ['id' => loteIdParaPortal($ordenA)]));
});

it('descarga el PDF del propio reporte técnico', function () {
    $contrato = contratoParaPortal('reporte-pdf');
    $orden = ordenParaPortal($contrato, 'reporte-pdf');
    $acta = actaFirmadaParaPortal($orden, 'reporte-pdf');
    $reporte = ReporteTecnico::query()
        ->where('trabajo_id', $acta->trabajo_id)
        ->firstOrFail();

    $usuario = cuentaPortalParaContrato($contrato, 'cliente.reporte.pdf');
    entrarAlPortal($usuario);

    $respuesta = $this->get(route('portal.reportes.pdf', $reporte->id));

    $respuesta->assertOk();
    expect($respuesta->headers->get('Content-Type'))->toContain('application/pdf');
});

it('cliente A pidiendo el reporte técnico de cliente B recibe 404', function () {
    $contratoA = contratoParaPortal('reporte-cruzado-a');
    $contratoB = contratoParaPortal('reporte-cruzado-b');

    actaFirmadaParaPortal(ordenParaPortal($contratoA, 'reporte-cruzado-a'), 'reporte-cruzado-a');
    $actaB = actaFirmadaParaPortal(ordenParaPortal($contratoB, 'reporte-cruzado-b'), 'reporte-cruzado-b');
    $reporteB = ReporteTecnico::query()
        ->where('trabajo_id', $actaB->trabajo_id)
        ->firstOrFail();

    $usuarioA = cuentaPortalParaContrato($contratoA, 'cliente.reporte.cruzado.a');
    entrarAlPortal($usuarioA);

    $this->get(route('portal.reportes.pdf', $reporteB->id))->assertNotFound();
});

it('un id de reporte inexistente recibe 404', function () {
    $contrato = contratoParaPortal('reporte-inexistente');
    $usuario = cuentaPortalParaContrato($contrato, 'cliente.reporte.inexistente');
    entrarAlPortal($usuario);

    $this->get(route('portal.reportes.pdf', 999999))->assertNotFound();
});

// --- Cuenta de portal sin contrato (tarea 68, revisión PR #106, P2) -------

it('una cuenta de portal sin contrato asociado recibe 404 en avance, actas y reportes', function () {
    $usuario = SecUser::factory()->create([
        'username' => 'cliente.sin.contrato',
        'password' => 'Secreta123',
        'type' => TipoUsuario::Cliente,
        'contrato_id' => null,
    ]);

    entrarAlPortal($usuario);

    $this->get(route('portal.avance.index'))->assertNotFound();
    $this->get(route('portal.actas.index'))->assertNotFound();
    $this->get(route('portal.reportes.index'))->assertNotFound();
});

// --- Guard del portal, por endpoint (tarea 68, revisión PR #106, P3) ------
//
// PortalClienteTest ya cubre guest/interno para portal.avance.index (arriba,
// mismo criterio, sin tocar). Estos dos `it` parametrizados replican ese
// mismo criterio (redirect a portal.login.form) para los cinco endpoints
// restantes del bloque `auth:cliente` de routes/web.php — el middleware es
// común a todo el grupo, pero CLAUDE.md pide un test por endpoint.

dataset('endpoints del portal protegidos por auth:cliente', [
    'actas.index' => ['portal.actas.index', 'get', []],
    'actas.pdf' => ['portal.actas.pdf', 'get', [1]],
    'reportes.index' => ['portal.reportes.index', 'get', []],
    'reportes.pdf' => ['portal.reportes.pdf', 'get', [1]],
    'preferencias.tema' => ['portal.preferencias.tema', 'post', []],
]);

it('exige sesión de portal (guest)', function (string $ruta, string $metodo, array $parametros) {
    $uri = route($ruta, $parametros);

    $respuesta = $metodo === 'post' ? $this->post($uri) : $this->get($uri);

    $respuesta->assertRedirect(route('portal.login.form'));
})->with('endpoints del portal protegidos por auth:cliente');

it('una sesión del panel interno no puede usarlo', function (string $ruta, string $metodo, array $parametros) {
    $usuario = SecUser::factory()->create(['type' => TipoUsuario::Interno]);
    $sesion = $this->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno');

    $uri = route($ruta, $parametros);
    $respuesta = $metodo === 'post' ? $sesion->post($uri) : $sesion->get($uri);

    $respuesta->assertRedirect(route('portal.login.form'));
})->with('endpoints del portal protegidos por auth:cliente');
