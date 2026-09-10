<?php

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rubro;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * HU-33 (tarea 47): "como encargado, quiero cargar gastos con su categoría y
 * comprobante, para que la campaña tenga costo real" (plan_sprints.md Sprint
 * 10 §218). CA esencial: ABM de gastos con evidencia adjunta; categorías de
 * catálogo (rubros/subrubros, sembrados por `FinanzasRubrosSeeder` vía
 * `CatalogoSeeder`); imputable a trabajo, base o general.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Storage::fake('r2');
});

function usuarioConRolParaGastos(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaGastos(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function encargadoEntraAlPanelParaGastos(): SecUser
{
    [$encargado, $idRol] = usuarioConRolParaGastos('encargado.gastos', 'encargado_operaciones');
    entrarAlPanelParaGastos($encargado, $idRol);

    return $encargado;
}

function rubroParaGastos(): Rubro
{
    return Rubro::query()->firstOrFail();
}

function baseParaGastos(): PerBase
{
    return PerBase::create(['nombre' => 'Base de gastos '.uniqid()]);
}

function equipoParaGastos(): EquipoTrabajo
{
    $base = baseParaGastos();

    return EquipoTrabajo::create([
        'codigo' => 'EQ-GAS-'.uniqid(),
        'base_id' => $base->id,
        'estado' => 'activo',
        'desde' => '2026-01-01',
    ]);
}

function trabajoParaGastos(): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de gastos '.uniqid(), 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo de gastos']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-GAS-'.uniqid(), 'hectareas' => '100.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '100.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '1000.00',
        'fecha_inicio' => '2026-01-01',
        'estado' => EstadoContrato::Vigente,
    ]);
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-01-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);

    return Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-gastos-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);
}

/** Payload mínimo válido de alta. */
function payloadGasto(int $rubroId, array $overrides = []): array
{
    return array_merge([
        'fecha' => '2026-09-20',
        'rubro_id' => $rubroId,
        'cantidad' => '10.00',
        'precio_unitario' => '5.00',
    ], $overrides);
}

it('calcula el monto exacto (cantidad × precio_unitario), sin error de redondeo flotante', function () {
    // 3.33 × 12.35 = 41.1255 -> HalfUp a 2 decimales = 41.13 (nunca
    // "41.125499999999995" de un float).
    $rubro = rubroParaGastos();
    encargadoEntraAlPanelParaGastos();

    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id, [
        'cantidad' => '3.33',
        'precio_unitario' => '12.35',
    ]))->assertRedirect(route('panel.gastos.index'));

    $gasto = Gasto::query()->sole();
    expect($gasto->monto)->toBe('41.13')
        ->and($gasto->cantidad)->toBe('3.33')
        ->and($gasto->precio_unitario)->toBe('12.35');
});

it('registra un gasto imputado a un trabajo, a una base, y general — los tres casos válidos', function () {
    $rubro = rubroParaGastos();
    $trabajo = trabajoParaGastos();
    $base = baseParaGastos();
    encargadoEntraAlPanelParaGastos();

    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id, ['trabajo_id' => $trabajo->id]))
        ->assertRedirect(route('panel.gastos.index'));

    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id, ['base_id' => $base->id]))
        ->assertRedirect(route('panel.gastos.index'));

    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id))
        ->assertRedirect(route('panel.gastos.index'));

    expect(Gasto::query()->count())->toBe(3);

    $gastoDeTrabajo = Gasto::query()->where('trabajo_id', $trabajo->id)->sole();
    expect($gastoDeTrabajo->base_id)->toBeNull();

    $gastoDeBase = Gasto::query()->where('base_id', $base->id)->sole();
    expect($gastoDeBase->trabajo_id)->toBeNull();

    $gastoGeneral = Gasto::query()->whereNull('trabajo_id')->whereNull('base_id')->sole();
    expect($gastoGeneral->id)->not->toBe($gastoDeTrabajo->id)
        ->and($gastoGeneral->id)->not->toBe($gastoDeBase->id);
});

it('guarda el comprobante subido con su hash SHA-256', function () {
    $rubro = rubroParaGastos();
    encargadoEntraAlPanelParaGastos();

    // Bytes reales de cabecera PDF (no una imagen GD: el contenedor de CI no
    // trae la extensión `gd`) — `finfo` los reconoce como `application/pdf`,
    // así la regla `mimes:pdf` pasa con contenido determinístico para el hash.
    $archivo = UploadedFile::fake()->createWithContent('comprobante.pdf', "%PDF-1.4\n%comprobante-demo-hash");
    $hashEsperado = (string) hash_file('sha256', $archivo->getRealPath());

    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id, ['comprobante' => $archivo]))
        ->assertRedirect(route('panel.gastos.index'));

    $gasto = Gasto::query()->sole();

    expect($gasto->comprobante_hash)->toBe($hashEsperado)
        ->and($gasto->comprobante_url)->not->toBeNull();

    Storage::disk('r2')->assertExists($gasto->comprobante_url);
});

it('rechaza un tipo de archivo de comprobante inválido', function () {
    $rubro = rubroParaGastos();
    encargadoEntraAlPanelParaGastos();

    $archivo = UploadedFile::fake()->createWithContent('comprobante.txt', 'esto no es una imagen ni un pdf');

    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id, ['comprobante' => $archivo]))
        ->assertRedirect()
        ->assertSessionHasErrors('comprobante');

    expect(Gasto::query()->count())->toBe(0);
});

it('un rol sin los permisos correspondientes recibe 403 en todas las acciones', function () {
    $rubro = rubroParaGastos();

    [$curioso, $idRol] = usuarioConRolParaGastos('piloto.curioso.gastos', 'piloto');
    entrarAlPanelParaGastos($curioso, $idRol);

    $gasto = Gasto::query()->create([
        'fecha' => '2026-09-20',
        'rubro_id' => $rubro->id,
        'cantidad' => '10.00',
        'precio_unitario' => '5.00',
        'monto' => '50.00',
    ]);

    $this->get(route('panel.gastos.index'))->assertForbidden();
    $this->get(route('panel.gastos.create'))->assertForbidden();
    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id))->assertForbidden();
    $this->delete(route('panel.gastos.destroy', $gasto))->assertForbidden();

    expect(Gasto::query()->count())->toBe(1)
        ->and($gasto->fresh()?->trashed())->toBeFalse();
});

it('registra en bitácora el alta y la baja de un gasto', function () {
    $rubro = rubroParaGastos();
    $encargado = encargadoEntraAlPanelParaGastos();

    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id));
    $gasto = Gasto::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'fin_gastos')
        ->where('registro_id', $gasto->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['monto'])->toBe('50.00');

    $this->delete(route('panel.gastos.destroy', $gasto))
        ->assertRedirect(route('panel.gastos.index'));

    Bitacora::query()
        ->where('tabla', 'fin_gastos')
        ->where('registro_id', $gasto->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja un gasto por soft delete: no lo borra físicamente ni aparece en el índice', function () {
    $rubro = rubroParaGastos();
    encargadoEntraAlPanelParaGastos();

    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id));
    $gasto = Gasto::query()->sole();

    $this->delete(route('panel.gastos.destroy', $gasto))
        ->assertRedirect(route('panel.gastos.index'));

    $borrado = Gasto::withTrashed()->findOrFail($gasto->id);
    expect($borrado->trashed())->toBeTrue()
        ->and(Gasto::query()->count())->toBe(0)
        ->and(Gasto::withTrashed()->count())->toBe(1);

    $this->get(route('panel.gastos.index'))
        ->assertOk()
        ->assertSee(__('finanzas.gastos.vacio'));
});

it('guarda un gasto sin campania_id: es un gasto interno que no pertenece a ninguna campaña', function () {
    $rubro = rubroParaGastos();
    encargadoEntraAlPanelParaGastos();

    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id))
        ->assertRedirect(route('panel.gastos.index'));

    $gasto = Gasto::query()->sole();
    expect($gasto->campania_id)->toBeNull();
});

it('registra un gasto atribuido a la campaña donde se consumió', function () {
    $rubro = rubroParaGastos();
    $cliente = Cliente::create(['razon_social' => 'Cliente de gastos '.uniqid(), 'tipo_persona' => 'juridica']);
    $campania = Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'abierta',
    ]);
    encargadoEntraAlPanelParaGastos();

    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id, ['campania_id' => $campania->id]))
        ->assertRedirect(route('panel.gastos.index'));

    $gasto = Gasto::query()->sole();
    expect($gasto->campania_id)->toBe($campania->id);
});

it('rechaza un gasto contra una campaña cerrada', function () {
    $rubro = rubroParaGastos();
    $cliente = Cliente::create(['razon_social' => 'Cliente de gastos '.uniqid(), 'tipo_persona' => 'juridica']);
    $campaniaCerrada = Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => '2024-2025',
        'fecha_inicio' => '2024-07-01',
        'fecha_fin' => '2025-06-30',
        'estado' => 'cerrada',
    ]);
    encargadoEntraAlPanelParaGastos();

    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id, ['campania_id' => $campaniaCerrada->id]))
        ->assertRedirect(route('panel.gastos.create'))
        ->assertSessionHasErrors('campania_id');

    expect(Gasto::query()->count())->toBe(0);
});

it('registra un gasto imputado a un equipo de trabajo (tarea 73, HU-50)', function () {
    $rubro = rubroParaGastos();
    $equipo = equipoParaGastos();
    encargadoEntraAlPanelParaGastos();

    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id, ['equipo_trabajo_id' => $equipo->id]))
        ->assertRedirect(route('panel.gastos.index'));

    expect(Gasto::query()->sole()->equipo_trabajo_id)->toBe($equipo->id);
});

it('filtra el listado por equipo y muestra el total exacto de ese equipo, en decimales de string', function () {
    // 0.10 + 0.20 en float da 0.30000000000000004 — acá tiene que dar
    // "0.30" exacto (BigDecimal, invariante 6 de CLAUDE.md).
    $rubro = rubroParaGastos();
    $equipoUno = equipoParaGastos();
    $equipoDos = equipoParaGastos();
    encargadoEntraAlPanelParaGastos();

    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id, [
        'equipo_trabajo_id' => $equipoUno->id,
        'cantidad' => '1',
        'precio_unitario' => '0.10',
    ]));
    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id, [
        'equipo_trabajo_id' => $equipoUno->id,
        'cantidad' => '1',
        'precio_unitario' => '0.20',
    ]));
    $this->post(route('panel.gastos.store'), payloadGasto($rubro->id, [
        'equipo_trabajo_id' => $equipoDos->id,
        'cantidad' => '1',
        'precio_unitario' => '999.99',
    ]));

    $respuesta = $this->get(route('panel.gastos.index', ['equipo_trabajo_id' => $equipoUno->id]));
    $respuesta->assertOk();

    expect($respuesta->viewData('gastos')->total())->toBe(2)
        ->and($respuesta->viewData('total'))->toBe('0.30');
});

it('publica el ítem de menú de gastos gateado por finanzas.gasto.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.financiero.items.gastos')->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'finanzas.gasto.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.gastos.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
