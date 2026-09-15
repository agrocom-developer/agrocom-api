<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Factura;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
 * HU-31 (tarea 45): "como encargado, quiero emitir la factura de un trabajo
 * desde su acta conformada, para cobrar sobre hectáreas ya firmadas" (espec
 * Sprint 9 §205). Factura solo desde acta `firmada`; monto = hectáreas
 * conformadas × precio_ha del contrato; no permite facturar dos veces el
 * mismo trabajo.
 *
 * El acta se genera y firma vía las rutas reales de `/api` (guard `sanctum`,
 * rol `piloto`), mismo criterio que `Api/ActaConformidadTest.php` — no se
 * inserta el estado `firmada` a mano por Eloquent, se ejercita el flujo real
 * hasta ahí. Las acciones del panel (`/panel/facturas*`) se ejercitan por
 * separado, con un usuario `encargado_operaciones` bajo el guard `interno`.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Storage::fake('r2');
});

function usuarioPilotoParaFacturas(): SecUser
{
    $usuario = SecUser::factory()->create();
    $idRolPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRolPiloto]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $usuario;
}

function ordenParaFacturas(string $sufijo, string $precioHa): OrdenAplicacion
{
    $cliente = Cliente::create(['razon_social' => "Cliente factura {$sufijo}", 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $lote = Lote::create(['propiedad_id' => $propiedad->id, 'codigo' => "L-FACT-{$sufijo}", 'hectareas' => '20.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '20.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => $precioHa,
        'monto_total' => '500.00',
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

/** Trabajo `cerrado` con su única sesión vigente `validado` — listo para generar el acta. */
function trabajoListoParaFacturas(string $sufijo, string $hectareas, string $precioHa): Trabajo
{
    $orden = ordenParaFacturas($sufijo, $precioHa);

    $trabajo = Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-factura-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => (int) $orden->ordenLotes()->value('lote_id'),
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => $hectareas,
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => '2026-09-01T08:00:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
    ]);

    $piloto = PerPersona::create(['nombre' => "Piloto factura {$sufijo}", 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    Sesion::create([
        'uuid_cliente' => "uuid-sesion-factura-{$sufijo}",
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

/** Genera (API) el acta del trabajo, sin firmarla — queda `pendiente`. @return array{0: Acta, 1: SecUser} */
function actaPendienteParaFacturas(string $sufijo, string $hectareas, string $precioHa = '25.00'): array
{
    $trabajo = trabajoListoParaFacturas($sufijo, $hectareas, $precioHa);
    $usuario = usuarioPilotoParaFacturas();

    test()->actingAs($usuario, 'sanctum')
        ->postJson("/api/trabajos/{$trabajo->uuid_cliente}/acta", ['uuid_cliente' => "uuid-acta-factura-{$sufijo}"])
        ->assertOk();

    $acta = Acta::query()->where('trabajo_id', $trabajo->id)->firstOrFail();

    return [$acta, $usuario];
}

/** Genera y firma (API) el acta del trabajo — queda `firmada`. */
function actaFirmadaParaFacturas(string $sufijo, string $hectareas, string $precioHa = '25.00'): Acta
{
    [$acta, $usuario] = actaPendienteParaFacturas($sufijo, $hectareas, $precioHa);

    $evidenciaUuid = "uuid-firma-factura-{$sufijo}";
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

function usuarioConRolParaFacturas(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaFacturas(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function encargadoEntraAlPanelParaFacturas(): SecUser
{
    [$encargado, $idRol] = usuarioConRolParaFacturas('encargado.facturas', 'encargado_operaciones');
    entrarAlPanelParaFacturas($encargado, $idRol);

    return $encargado;
}

it('crea la tabla com_facturas con soft delete y columnas de auditoría', function () {
    expect(Schema::hasTable('com_facturas'))->toBeTrue()
        ->and(Schema::hasColumns('com_facturas', [
            'contrato_id', 'acta_id', 'hectareas_facturadas', 'precio_ha', 'monto', 'fecha_emision',
            'deleted_at', 'created_by', 'updated_by', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

it('emite una factura desde un acta firmada, con el monto exacto (hectáreas conformadas × precio_ha)', function () {
    $acta = actaFirmadaParaFacturas('ok', '18.50', '25.00');

    encargadoEntraAlPanelParaFacturas();

    $this->post(route('panel.facturas.store'), ['acta_id' => $acta->id])
        ->assertRedirect(route('panel.facturas.index'));

    $factura = Factura::query()->where('acta_id', $acta->id)->sole();

    expect($factura->contrato_id)->not->toBeNull()
        ->and($factura->hectareas_facturadas)->toBe('18.50')
        ->and($factura->precio_ha)->toBe('25.00')
        ->and($factura->monto)->toBe('462.50');
});

it('rechaza emitir desde un acta pendiente (sin firmar)', function () {
    [$acta] = actaPendienteParaFacturas('pendiente', '10.00');

    encargadoEntraAlPanelParaFacturas();

    $this->post(route('panel.facturas.store'), ['acta_id' => $acta->id])
        ->assertRedirect()
        ->assertSessionHasErrors('acta_id');

    expect(Factura::query()->count())->toBe(0);
});

it('rechaza una segunda emisión sobre la misma acta ya facturada', function () {
    $acta = actaFirmadaParaFacturas('doble', '12.00', '30.00');

    encargadoEntraAlPanelParaFacturas();

    $this->post(route('panel.facturas.store'), ['acta_id' => $acta->id])
        ->assertRedirect(route('panel.facturas.index'));

    $this->post(route('panel.facturas.store'), ['acta_id' => $acta->id])
        ->assertRedirect()
        ->assertSessionHasErrors('acta_id');

    expect(Factura::query()->where('acta_id', $acta->id)->count())->toBe(1);
});

it('el monto queda exacto en DECIMAL, sin error de redondeo flotante (caso 3.33 × 12.35)', function () {
    // Mismo caso que la tarea 16/41: hectareas 3.33 × precio 12.35 => monto
    // exacto 41.13 (BigDecimal con HalfUp, nunca float).
    $acta = actaFirmadaParaFacturas('decimal', '3.33', '12.35');

    encargadoEntraAlPanelParaFacturas();

    $this->post(route('panel.facturas.store'), ['acta_id' => $acta->id])
        ->assertRedirect(route('panel.facturas.index'));

    $factura = Factura::query()->where('acta_id', $acta->id)->sole();
    expect($factura->monto)->toBe('41.13');
});

it('el alta en bitácora registra la emisión de la factura', function () {
    $acta = actaFirmadaParaFacturas('bitacora', '15.00', '20.00');

    $encargado = encargadoEntraAlPanelParaFacturas();

    $this->post(route('panel.facturas.store'), ['acta_id' => $acta->id])
        ->assertRedirect(route('panel.facturas.index'));

    $factura = Factura::query()->where('acta_id', $acta->id)->sole();

    $filaCreada = Bitacora::query()
        ->where('tabla', 'com_facturas')
        ->where('registro_id', $factura->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreada->user_id)->toBe($encargado->id)
        ->and($filaCreada->despues['monto'])->toBe('300.00');
});

it('el alta de una acta firmada y facturada desaparece del selector de actas disponibles', function () {
    $actaFacturada = actaFirmadaParaFacturas('ya-facturada', '10.00', '10.00');
    $actaDisponible = actaFirmadaParaFacturas('disponible', '5.00', '10.00');

    encargadoEntraAlPanelParaFacturas();

    $this->post(route('panel.facturas.store'), ['acta_id' => $actaFacturada->id])
        ->assertRedirect(route('panel.facturas.index'));

    $this->get(route('panel.facturas.create'))
        ->assertOk()
        ->assertDontSee("Acta #{$actaFacturada->id}")
        ->assertSee("Acta #{$actaDisponible->id}");
});

it('un rol sin los permisos correspondientes recibe 403 en todas las acciones', function () {
    $acta = actaFirmadaParaFacturas('sinpermiso', '10.00', '10.00');

    [$curioso, $idRol] = usuarioConRolParaFacturas('piloto.curioso.facturas', 'piloto');
    entrarAlPanelParaFacturas($curioso, $idRol);

    $this->get(route('panel.facturas.index'))->assertForbidden();
    $this->get(route('panel.facturas.create'))->assertForbidden();
    $this->post(route('panel.facturas.store'), ['acta_id' => $acta->id])->assertForbidden();

    expect(Factura::query()->count())->toBe(0);
});

it('publica el ítem de menú de facturas gateado por comercial.factura.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.financiero.items.facturas')->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'comercial.factura.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.facturas.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
