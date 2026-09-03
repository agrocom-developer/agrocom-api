<?php

use App\Dominios\Comercial\Aplicacion\EmitirFactura;
use App\Dominios\Comercial\Aplicacion\ObtenerAvanceComercial;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
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
 * HU-32 (tarea 46): "como dueño, quiero un reporte comercial de avance por
 * cliente, contrato y campaña, para saber cuánto queda por aplicar y por
 * cobrar" (espec Sprint 9 §206) — cierra Sprint 9. Por contrato: hectáreas
 * contratadas (columna propia), aplicadas (suma de actas FIRMADAS, estén o
 * no facturadas) y facturadas + monto (com_facturas). Exportable a CSV.
 *
 * Las actas se generan y firman vía las rutas reales de `/api` (guard
 * `sanctum`, rol `piloto`), mismo criterio que `FacturasPanelTest` — no se
 * inserta el estado `firmada` a mano por Eloquent. Las acciones del panel
 * (`/panel/reportes/comercial*`) se ejercitan por separado, con guard
 * `interno`. `comercial.reporte.ver` es exclusivo del dueño (no entra en
 * PERMISOS_ENCARGADO_OPERACIONES de SeguridadSeeder).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Storage::fake('r2');
});

function clienteParaAvance(string $sufijo): Cliente
{
    return Cliente::create(['razon_social' => "Cliente avance {$sufijo}"]);
}

function contratoParaAvance(string $sufijo, string $hectareasContratadas, string $precioHa, ?int $clienteId = null): Contrato
{
    return Contrato::create([
        'cliente_id' => $clienteId ?? clienteParaAvance($sufijo)->id,
        'hectareas_contratadas' => $hectareasContratadas,
        'aplicaciones_previstas' => 1,
        'precio_ha' => $precioHa,
        'monto_total' => '0.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);
}

function ordenParaAvance(Contrato $contrato, string $sufijo): OrdenAplicacion
{
    $campo = Campo::create(['cliente_id' => $contrato->cliente_id, 'nombre' => "Campo avance {$sufijo}"]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => "L-AVANCE-{$sufijo}", 'hectareas' => '50.00']);

    return OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
}

function usuarioPilotoParaAvance(): SecUser
{
    $usuario = SecUser::factory()->create();
    $idRolPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRolPiloto]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $usuario;
}

/** Trabajo `cerrado` con su única sesión vigente `validado` — listo para generar el acta. */
function trabajoListoParaAvance(OrdenAplicacion $orden, string $sufijo, string $hectareas): Trabajo
{
    $trabajo = Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-avance-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => $hectareas,
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => '2026-09-01T08:00:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
    ]);

    $piloto = PerPersona::create(['nombre' => "Piloto avance {$sufijo}", 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    Sesion::create([
        'uuid_cliente' => "uuid-sesion-avance-{$sufijo}",
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => $hectareas,
        'estado' => EstadoSesion::Validado,
        'inicio' => '2026-09-01T08:05:00-04:00',
        'fin' => '2026-09-01T11:00:00-04:00',
        'motivo_cierre' => 'completado',
    ]);

    return $trabajo;
}

/** Genera y firma (API) el acta del trabajo — queda `firmada`. */
function actaFirmadaParaAvance(string $sufijo, OrdenAplicacion $orden, string $hectareas): Acta
{
    $trabajo = trabajoListoParaAvance($orden, $sufijo, $hectareas);
    $usuario = usuarioPilotoParaAvance();

    test()->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => "uuid-acta-avance-{$sufijo}"])
        ->assertOk();

    $acta = Acta::query()->where('trabajo_id', $trabajo->id)->firstOrFail();

    $evidenciaUuid = "uuid-firma-avance-{$sufijo}";
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

function usuarioConRolParaAvance(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaAvance(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function duenoEntraAlPanelParaAvance(): SecUser
{
    [$dueno, $idRol] = usuarioConRolParaAvance('dueno.avance.'.uniqid(), 'dueno');
    entrarAlPanelParaAvance($dueno, $idRol);

    return $dueno;
}

it('agrega hectáreas contratadas, aplicadas (sumando varias actas) y facturadas con monto exacto', function () {
    $contrato = contratoParaAvance('agg', '100.00', '25.00');
    $orden = ordenParaAvance($contrato, 'agg');

    $actaA = actaFirmadaParaAvance('agg-a', $orden, '10.50');
    actaFirmadaParaAvance('agg-b', $orden, '20.25');

    app(EmitirFactura::class)->ejecutar($actaA->id);

    $avance = app(ObtenerAvanceComercial::class)->ejecutar();
    $fila = collect($avance)->firstWhere('contratoId', $contrato->id);

    expect($fila)->not->toBeNull()
        ->and($fila['clienteNombre'])->toBe($contrato->cliente->razon_social)
        ->and($fila['hectareasContratadas'])->toBe('100.00')
        ->and($fila['hectareasAplicadas'])->toBe('30.75')
        ->and($fila['hectareasFacturadas'])->toBe('10.50')
        ->and($fila['montoFacturado'])->toBe('262.50');
});

it('un contrato sin actas firmadas aparece con aplicadas y facturadas en 0.00, sin caerse', function () {
    $contrato = contratoParaAvance('vacio', '40.00', '15.00');

    $avance = app(ObtenerAvanceComercial::class)->ejecutar();
    $fila = collect($avance)->firstWhere('contratoId', $contrato->id);

    expect($fila)->not->toBeNull()
        ->and($fila['hectareasAplicadas'])->toBe('0.00')
        ->and($fila['hectareasFacturadas'])->toBe('0.00')
        ->and($fila['montoFacturado'])->toBe('0.00');
});

it('el monto y las hectáreas quedan exactos en DECIMAL, sin error de redondeo flotante (caso 3.33 × 12.35)', function () {
    // Mismo caso que las tareas 16/41/45: hectareas 3.33 × precio 12.35 =>
    // monto exacto 41.13 (BigDecimal con HalfUp, nunca float).
    $contrato = contratoParaAvance('decimal', '33.33', '12.35');
    $orden = ordenParaAvance($contrato, 'decimal');
    $acta = actaFirmadaParaAvance('decimal', $orden, '3.33');

    app(EmitirFactura::class)->ejecutar($acta->id);

    $avance = app(ObtenerAvanceComercial::class)->ejecutar();
    $fila = collect($avance)->firstWhere('contratoId', $contrato->id);

    expect($fila['hectareasFacturadas'])->toBe('3.33')
        ->and($fila['montoFacturado'])->toBe('41.13');
});

it('el caso de uso filtra por cliente_id y por contrato_id', function () {
    $contratoA = contratoParaAvance('filtroA', '10.00', '5.00');
    $contratoB = contratoParaAvance('filtroB', '20.00', '5.00');

    $porCliente = app(ObtenerAvanceComercial::class)->ejecutar(clienteId: $contratoA->cliente_id);
    $porContrato = app(ObtenerAvanceComercial::class)->ejecutar(contratoId: $contratoB->id);

    expect(collect($porCliente)->pluck('contratoId')->all())->toBe([$contratoA->id])
        ->and(collect($porContrato)->pluck('contratoId')->all())->toBe([$contratoB->id]);
});

it('la pantalla responde 200 con el rol dueño', function () {
    contratoParaAvance('pantalla', '12.00', '8.00');

    duenoEntraAlPanelParaAvance();

    $this->get(route('panel.reportes.comercial.index'))
        ->assertOk()
        ->assertSee('Cliente avance pantalla');
});

it('filtra la pantalla por cliente y por contrato', function () {
    // Las hectáreas contratadas identifican la FILA de la tabla sin
    // ambigüedad: "Contrato #N" y el nombre del cliente también aparecen
    // en las opciones de los <select> de filtro (que siempre listan TODOS
    // los contratos/clientes disponibles, filtrados o no).
    $contratoA = contratoParaAvance('vistaA', '10.00', '5.00');
    $contratoB = contratoParaAvance('vistaB', '20.00', '5.00');

    duenoEntraAlPanelParaAvance();

    $this->get(route('panel.reportes.comercial.index', ['contrato_id' => $contratoA->id]))
        ->assertOk()
        ->assertSee('10,00 ha')
        ->assertDontSee('20,00 ha');
});

it('un rol sin el permiso recibe 403, incluido encargado_operaciones', function () {
    [$encargado, $idRol] = usuarioConRolParaAvance('encargado.avance', 'encargado_operaciones');
    entrarAlPanelParaAvance($encargado, $idRol);

    $this->get(route('panel.reportes.comercial.index'))->assertForbidden();
    $this->get(route('panel.reportes.comercial.exportar'))->assertForbidden();
});

it('exporta un CSV con las filas esperadas respetando el filtro aplicado', function () {
    $contratoA = contratoParaAvance('csvA', '10.00', '5.00');
    $contratoB = contratoParaAvance('csvB', '20.00', '5.00');

    duenoEntraAlPanelParaAvance();

    $respuesta = $this->get(route('panel.reportes.comercial.exportar', ['contrato_id' => $contratoA->id]));

    $respuesta->assertOk();
    expect($respuesta->headers->get('Content-Type'))->toContain('text/csv');

    $contenido = $respuesta->streamedContent();

    expect($contenido)->toContain($contratoA->cliente->razon_social)
        ->and($contenido)->toContain((string) $contratoA->id)
        ->and($contenido)->not->toContain($contratoB->cliente->razon_social);
});

it('publica el ítem de menú de reportes comerciales gateado por comercial.reporte.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.reportes.items.comerciales')->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'comercial.reporte.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.reportes.comercial.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
