<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Inventario\Infraestructura\Eloquent\MovimientoStock;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-36 (tarea 52): catálogo de repuestos con stock por base y alerta de
 * mínimo. Módulo nuevo `Inventario` (`inv_`, ADR 0011, extensión 3/9/2026,
 * punto 15), separado de `Mantenimiento`. Permisos evaluados contra el ROL
 * ACTIVO de la sesión, nunca la unión de los roles del usuario (invariante
 * 10 de CLAUDE.md). Mismo patrón de asserts que
 * tests/Feature/Mantenimiento/GestionBateriasPanelTest.php (tarea 51).
 *
 * Los casos de "rol con el permiso" usan `dueno`, no `encargado_operaciones`:
 * HU-88 (tarea 103) le sacó a este último el catálogo completo de repuestos y
 * stock (dejaba un hueco raro con solo `.ver` afuera, ver
 * `SeguridadSeeder::PERMISOS_ENCARGADO_OPERACIONES`) para que la HU sacara
 * los ítems de su menú.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaInventario(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

/** Entra al panel con un rol activo fijado, como haría el login. */
function entrarAlPanelParaInventario(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de alta/edición de repuesto. */
function payloadRepuesto(array $overrides = []): array
{
    return array_merge([
        'codigo' => 'REP-001',
        'descripcion' => 'Hélice 15 pulgadas',
        'unidad' => 'unidad',
        'costo_unitario' => '',
    ], $overrides);
}

/** Payload mínimo válido de un movimiento de stock: una compra completa. */
function payloadMovimiento(array $overrides = []): array
{
    return array_merge([
        'tipo' => 'compra',
        'repuesto_id' => '',
        'base_id' => '',
        'base_destino_id' => '',
        'cantidad' => '10',
        'sentido' => '',
        'costo_unitario' => '5.00',
        'motivo' => '',
    ], $overrides);
}

it('da de alta un repuesto con código, descripción, unidad y costo válidos', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $this->post(route('panel.repuestos.store'), payloadRepuesto(['costo_unitario' => '45.50']))
        ->assertRedirect(route('panel.repuestos.index'));

    $repuesto = Repuesto::query()->where('codigo', 'REP-001')->sole();

    expect($repuesto->descripcion)->toBe('Hélice 15 pulgadas')
        ->and($repuesto->unidad)->toBe('unidad')
        ->and($repuesto->costo_unitario)->toBe('45.50');
});

it('da de alta un repuesto sin costo unitario, todavía sin ninguna compra', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $this->post(route('panel.repuestos.store'), payloadRepuesto())
        ->assertRedirect(route('panel.repuestos.index'));

    $repuesto = Repuesto::query()->where('codigo', 'REP-001')->sole();
    expect($repuesto->costo_unitario)->toBeNull();
});

it('el código duplicado entre repuestos activos es un error de validación, no un QueryException', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Otro', 'unidad' => 'unidad']);

    $this->post(route('panel.repuestos.store'), payloadRepuesto())
        ->assertSessionHasErrors('codigo');

    expect(Repuesto::query()->where('descripcion', 'Hélice 15 pulgadas')->exists())->toBeFalse();
});

it('un repuesto dado de baja no bloquea el re-alta con el mismo código', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $existente = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Viejo', 'unidad' => 'unidad']);
    $existente->delete();

    $this->post(route('panel.repuestos.store'), payloadRepuesto())
        ->assertRedirect(route('panel.repuestos.index'));

    expect(Repuesto::query()->where('codigo', 'REP-001')->count())->toBe(1);
});

it('edita un repuesto existente', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad', 'costo_unitario' => '10.00']);

    $this->put(
        route('panel.repuestos.update', $repuesto),
        payloadRepuesto(['codigo' => 'REP-001-B', 'descripcion' => 'Hélice reforzada', 'costo_unitario' => '20.00']),
    )->assertRedirect(route('panel.repuestos.index'));

    $repuesto->refresh();
    expect($repuesto->codigo)->toBe('REP-001-B')
        ->and($repuesto->descripcion)->toBe('Hélice reforzada')
        ->and($repuesto->costo_unitario)->toBe('20.00');
});

it('filtra el listado de repuestos por código o descripción', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    Repuesto::query()->create(['codigo' => 'REP-HELICE', 'descripcion' => 'Hélice', 'unidad' => 'unidad']);
    Repuesto::query()->create(['codigo' => 'REP-MOTOR', 'descripcion' => 'Motor', 'unidad' => 'unidad']);

    $this->get(route('panel.repuestos.index', ['q' => 'HELICE']))
        ->assertOk()
        ->assertSee('REP-HELICE')
        ->assertDontSee('REP-MOTOR');
});

it('registra una compra: incrementa el stock de la base y sobrescribe el costo unitario del repuesto', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);

    $this->post(route('panel.stock.movimientos.store'), payloadMovimiento([
        'tipo' => 'compra',
        'repuesto_id' => (string) $repuesto->id,
        'base_id' => (string) $base->id,
        'cantidad' => '15.50',
        'costo_unitario' => '8.25',
    ]))->assertRedirect(route('panel.stock.index'));

    $stock = Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $base->id)->sole();
    expect($stock->cantidad)->toBe('15.50');

    expect($repuesto->fresh()->costo_unitario)->toBe('8.25');

    $movimiento = MovimientoStock::query()->sole();
    expect($movimiento->tipo)->toBe('compra')
        ->and($movimiento->cantidad)->toBe('15.50')
        ->and($movimiento->costo_unitario)->toBe('8.25')
        ->and($movimiento->sentido)->toBeNull();
});

it('registra una salida: decrementa el stock existente', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '20.00', 'stock_minimo' => '0']);

    $this->post(route('panel.stock.movimientos.store'), payloadMovimiento([
        'tipo' => 'salida',
        'repuesto_id' => (string) $repuesto->id,
        'base_id' => (string) $base->id,
        'cantidad' => '5.00',
    ]))->assertRedirect(route('panel.stock.index'));

    $stock = Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $base->id)->sole();
    expect($stock->cantidad)->toBe('15.00');
});

it('registra un ajuste por incremento: suma stock con el motivo declarado', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '10.00', 'stock_minimo' => '0']);

    $this->post(route('panel.stock.movimientos.store'), payloadMovimiento([
        'tipo' => 'ajuste',
        'repuesto_id' => (string) $repuesto->id,
        'base_id' => (string) $base->id,
        'cantidad' => '3.00',
        'sentido' => 'incremento',
        'motivo' => 'Conteo físico encontró más unidades',
    ]))->assertRedirect(route('panel.stock.index'));

    $stock = Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $base->id)->sole();
    expect($stock->cantidad)->toBe('13.00');

    $movimiento = MovimientoStock::query()->sole();
    expect($movimiento->sentido)->toBe('incremento')
        ->and($movimiento->motivo)->toBe('Conteo físico encontró más unidades');
});

it('registra un ajuste por decremento: resta stock con el motivo declarado', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '10.00', 'stock_minimo' => '0']);

    $this->post(route('panel.stock.movimientos.store'), payloadMovimiento([
        'tipo' => 'ajuste',
        'repuesto_id' => (string) $repuesto->id,
        'base_id' => (string) $base->id,
        'cantidad' => '4.00',
        'sentido' => 'decremento',
        'motivo' => 'Rotura detectada en el conteo',
    ]))->assertRedirect(route('panel.stock.index'));

    $stock = Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $base->id)->sole();
    expect($stock->cantidad)->toBe('6.00');
});

it('registra un traslado: decrementa la base de origen e incrementa la de destino desde un único movimiento', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad']);
    $baseOrigen = PerBase::query()->create(['nombre' => 'Base Norte']);
    $baseDestino = PerBase::query()->create(['nombre' => 'Base Sur']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $baseOrigen->id, 'cantidad' => '10.00', 'stock_minimo' => '0']);

    $this->post(route('panel.stock.movimientos.store'), payloadMovimiento([
        'tipo' => 'traslado',
        'repuesto_id' => (string) $repuesto->id,
        'base_id' => (string) $baseOrigen->id,
        'base_destino_id' => (string) $baseDestino->id,
        'cantidad' => '6.00',
        'motivo' => 'Redistribución entre bases',
    ]))->assertRedirect(route('panel.stock.index'));

    $stockOrigen = Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $baseOrigen->id)->sole();
    $stockDestino = Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $baseDestino->id)->sole();

    expect($stockOrigen->cantidad)->toBe('4.00')
        ->and($stockDestino->cantidad)->toBe('6.00')
        ->and(MovimientoStock::query()->count())->toBe(1);

    $movimiento = MovimientoStock::query()->sole();
    expect($movimiento->base_id)->toBe($baseOrigen->id)
        ->and($movimiento->base_destino_id)->toBe($baseDestino->id);
});

it('rechaza una salida que dejaría el stock negativo: error de validación, no un 500 ni el CHECK de la base', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '5.00', 'stock_minimo' => '0']);

    $this->post(route('panel.stock.movimientos.store'), payloadMovimiento([
        'tipo' => 'salida',
        'repuesto_id' => (string) $repuesto->id,
        'base_id' => (string) $base->id,
        'cantidad' => '9.00',
    ]))->assertSessionHasErrors('cantidad');

    expect(Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $base->id)->sole()->cantidad)->toBe('5.00')
        ->and(MovimientoStock::query()->count())->toBe(0);
});

it('rechaza un traslado que dejaría la base de origen en negativo, sin tocar ninguna de las dos filas de stock', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad']);
    $baseOrigen = PerBase::query()->create(['nombre' => 'Base Norte']);
    $baseDestino = PerBase::query()->create(['nombre' => 'Base Sur']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $baseOrigen->id, 'cantidad' => '3.00', 'stock_minimo' => '0']);

    $this->post(route('panel.stock.movimientos.store'), payloadMovimiento([
        'tipo' => 'traslado',
        'repuesto_id' => (string) $repuesto->id,
        'base_id' => (string) $baseOrigen->id,
        'base_destino_id' => (string) $baseDestino->id,
        'cantidad' => '9.00',
        'motivo' => 'Redistribución entre bases',
    ]))->assertSessionHasErrors('cantidad');

    expect(Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $baseOrigen->id)->sole()->cantidad)->toBe('3.00')
        ->and(Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $baseDestino->id)->exists())->toBeFalse()
        ->and(MovimientoStock::query()->count())->toBe(0);
});

it('activa la alerta cuando la cantidad cae al mínimo o por debajo, no antes', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $repuesto = Repuesto::query()->create(['codigo' => 'REP-ALERTA', 'descripcion' => 'Hélice', 'unidad' => 'unidad']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '6.00', 'stock_minimo' => '5.00']);

    $this->get(route('panel.stock.index', ['q' => 'REP-ALERTA']))
        ->assertOk()
        ->assertDontSee(__('inventario.stock.alerta_activa'));

    $stock = Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $base->id)->sole();
    $stock->cantidad = '5.00';
    $stock->save();

    $this->get(route('panel.stock.index', ['q' => 'REP-ALERTA']))
        ->assertOk()
        ->assertSee(__('inventario.stock.alerta_activa'));
});

it('registra en bitácora el alta y la edición de un repuesto, y el alta de un movimiento', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $this->post(route('panel.repuestos.store'), payloadRepuesto());
    $repuesto = Repuesto::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'inv_repuestos')
        ->where('registro_id', $repuesto->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($dueno->id)
        ->and($filaCreado->despues['codigo'])->toBe('REP-001');

    $this->put(route('panel.repuestos.update', $repuesto), payloadRepuesto(['codigo' => 'REP-001-B']))
        ->assertRedirect(route('panel.repuestos.index'));

    Bitacora::query()
        ->where('tabla', 'inv_repuestos')
        ->where('registro_id', $repuesto->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);

    $this->post(route('panel.stock.movimientos.store'), payloadMovimiento([
        'repuesto_id' => (string) $repuesto->id,
        'base_id' => (string) $base->id,
    ]))->assertRedirect(route('panel.stock.index'));

    $movimiento = MovimientoStock::query()->sole();

    $filaMovimiento = Bitacora::query()
        ->where('tabla', 'inv_movimientos')
        ->where('registro_id', $movimiento->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaMovimiento->user_id)->toBe($dueno->id)
        ->and($filaMovimiento->despues['tipo'])->toBe('compra');
});

it('da de baja un repuesto por soft delete: no aparece en el índice y un segundo intento da 404', function () {
    [$dueno, $idRol] = usuarioConRolParaInventario('dueno', 'dueno');
    entrarAlPanelParaInventario($dueno, $idRol);

    $this->post(route('panel.repuestos.store'), payloadRepuesto());
    $repuesto = Repuesto::query()->sole();

    $this->delete(route('panel.repuestos.destroy', $repuesto))
        ->assertRedirect(route('panel.repuestos.index'));

    $borrado = Repuesto::withTrashed()->findOrFail($repuesto->id);
    expect($borrado->trashed())->toBeTrue();

    $this->get(route('panel.repuestos.index'))
        ->assertOk()
        ->assertDontSee('REP-001');

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.repuestos.destroy', $repuesto))->assertNotFound();
});

it('un rol sin el permiso recibe 403 en todas las acciones de repuestos y de stock', function () {
    [$piloto, $idRol] = usuarioConRolParaInventario('piloto.curioso', 'piloto');
    entrarAlPanelParaInventario($piloto, $idRol);

    $repuesto = Repuesto::query()->create(['codigo' => 'REP-EXISTENTE', 'descripcion' => 'Hélice', 'unidad' => 'unidad']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);

    $this->get(route('panel.repuestos.index'))->assertForbidden();
    $this->get(route('panel.repuestos.create'))->assertForbidden();
    $this->post(route('panel.repuestos.store'), payloadRepuesto())->assertForbidden();
    $this->get(route('panel.repuestos.edit', $repuesto))->assertForbidden();
    $this->put(route('panel.repuestos.update', $repuesto), payloadRepuesto())->assertForbidden();
    $this->delete(route('panel.repuestos.destroy', $repuesto))->assertForbidden();

    $this->get(route('panel.stock.index'))->assertForbidden();
    $this->get(route('panel.stock.movimientos.create'))->assertForbidden();
    $this->post(route('panel.stock.movimientos.store'), payloadMovimiento([
        'repuesto_id' => (string) $repuesto->id,
        'base_id' => (string) $base->id,
    ]))->assertForbidden();

    expect(Repuesto::query()->count())->toBe(1)
        ->and($repuesto->fresh()?->trashed())->toBeFalse()
        ->and(MovimientoStock::query()->count())->toBe(0);
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: dueño (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    [$multirol, $idDueno] = usuarioConRolParaInventario('jefe.multirol', 'dueno');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaInventario($multirol, $idPiloto);
    $this->post(route('panel.repuestos.store'), payloadRepuesto())->assertForbidden();
    expect(Repuesto::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaInventario($multirol, $idDueno);
    $this->post(route('panel.repuestos.store'), payloadRepuesto())->assertRedirect();
    expect(Repuesto::query()->count())->toBe(1);
});

it('publica los ítems de menú de repuestos y de stock, gateados por sus permisos de ver', function () {
    $itemRepuestos = SecMenu::query()
        ->where('label', 'menu.mantenimiento.items.repuestos')
        ->sole();

    $idPermisoRepuestos = (int) SecPermission::query()
        ->where('code', 'inventario.repuesto.ver')
        ->value('id');

    expect($itemRepuestos->ruta)->toBe('panel.repuestos.index')
        ->and($itemRepuestos->permission_id)->toBe($idPermisoRepuestos);

    $itemStock = SecMenu::query()
        ->where('label', 'menu.mantenimiento.items.stock')
        ->sole();

    $idPermisoStock = (int) SecPermission::query()
        ->where('code', 'inventario.movimiento.ver')
        ->value('id');

    expect($itemStock->ruta)->toBe('panel.stock.index')
        ->and($itemStock->permission_id)->toBe($idPermisoStock);
});
