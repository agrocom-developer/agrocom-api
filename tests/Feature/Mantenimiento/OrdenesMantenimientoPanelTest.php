<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use App\Dominios\Inventario\Infraestructura\Eloquent\MovimientoStock;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
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
 * HU-37 (tarea 53): órdenes de mantenimiento que consumen stock y generan
 * gasto — cierra sobre `Inventario` (tarea 52) y `Finanzas` (tarea 47) vía
 * sus contratos de escritura (`EscrituraConsumoStock`/
 * `EscrituraGastoMantenimiento`). Permisos evaluados contra el ROL ACTIVO de
 * la sesión, nunca la unión de los roles del usuario (invariante 10 de
 * CLAUDE.md). Mismo patrón de asserts que
 * tests/Feature/Inventario/GestionStockPanelTest.php (tarea 52).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaOrdenesMantenimiento(string $username, string $rol): array
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
function entrarAlPanelParaOrdenesMantenimiento(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de apertura de una orden sobre un dron. */
function payloadOrdenMantenimiento(array $overrides = []): array
{
    return array_merge([
        'equipo_tipo' => 'dron',
        'equipo_id' => '',
        'tipo' => 'preventivo',
        'descripcion' => 'Revisión de rutina',
    ], $overrides);
}

it('abre una orden de mantenimiento válida sobre un dron', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenesMantenimiento('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenesMantenimiento($encargado, $idRol);

    $dron = Dron::query()->create(['identificador' => 'DRN-001']);

    $this->post(route('panel.ordenes-mantenimiento.store'), payloadOrdenMantenimiento(['equipo_id' => (string) $dron->id]))
        ->assertRedirect(route('panel.ordenes-mantenimiento.index'));

    $orden = OrdenMantenimiento::query()->sole();
    expect($orden->equipo_tipo)->toBe('dron')
        ->and($orden->equipo_id)->toBe($dron->id)
        ->and($orden->tipo)->toBe('preventivo')
        ->and($orden->estado->value)->toBe('abierta')
        ->and($orden->fecha_cierre)->toBeNull()
        ->and($orden->gasto_id)->toBeNull();
});

it('cierra una orden con stock suficiente: descuenta el stock exacto y crea un gasto con el monto exacto', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenesMantenimiento('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenesMantenimiento($encargado, $idRol);

    $dron = Dron::query()->create(['identificador' => 'DRN-001']);
    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad', 'costo_unitario' => '8.25']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '20.00', 'stock_minimo' => '0']);

    $this->post(route('panel.ordenes-mantenimiento.store'), payloadOrdenMantenimiento(['equipo_id' => (string) $dron->id]));
    $orden = OrdenMantenimiento::query()->sole();

    $this->post(route('panel.ordenes-mantenimiento.cerrar', $orden), [
        'repuestos' => [
            ['repuesto_id' => (string) $repuesto->id, 'base_id' => (string) $base->id, 'cantidad' => '5.00'],
        ],
        'descripcion_final' => 'Se cambió la hélice dañada.',
    ])->assertRedirect(route('panel.ordenes-mantenimiento.index'));

    $stock = Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $base->id)->sole();
    expect($stock->cantidad)->toBe('15.00');

    $movimiento = MovimientoStock::query()->sole();
    expect($movimiento->tipo)->toBe('salida')
        ->and($movimiento->cantidad)->toBe('5.00')
        ->and($movimiento->orden_mantenimiento_id)->toBe($orden->id);

    $gasto = Gasto::query()->sole();
    expect($gasto->monto)->toBe('41.25')
        ->and($gasto->cantidad)->toBe('1.00')
        ->and($gasto->precio_unitario)->toBe('41.25');

    $orden->refresh();
    expect($orden->estado->value)->toBe('cerrada')
        ->and($orden->gasto_id)->toBe($gasto->id)
        ->and($orden->fecha_cierre)->not->toBeNull()
        // HU-89 (tarea 104): la descripción final del cierre se persiste
        // sin pisar la descripción de apertura.
        ->and($orden->descripcion_final)->toBe('Se cambió la hélice dañada.')
        ->and($orden->descripcion)->toBe('Revisión de rutina');
});

it('el detalle de una orden cerrada muestra el precio final real, igual a fin_gastos.monto (HU-88, tarea 103)', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenesMantenimiento('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenesMantenimiento($encargado, $idRol);

    $dron = Dron::query()->create(['identificador' => 'DRN-001']);
    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad', 'costo_unitario' => '8.25']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '20.00', 'stock_minimo' => '0']);

    $this->post(route('panel.ordenes-mantenimiento.store'), payloadOrdenMantenimiento(['equipo_id' => (string) $dron->id]));
    $orden = OrdenMantenimiento::query()->sole();

    $this->post(route('panel.ordenes-mantenimiento.cerrar', $orden), [
        'repuestos' => [
            ['repuesto_id' => (string) $repuesto->id, 'base_id' => (string) $base->id, 'cantidad' => '5.00'],
        ],
        'descripcion_final' => 'Se cambió la hélice dañada.',
    ]);

    $gasto = Gasto::query()->sole();
    expect($gasto->monto)->toBe('41.25');

    // El monto mostrado sale de `fin_gastos.monto` vía el contrato de
    // lectura de `Finanzas` (`LecturaGastoMantenimiento`), nunca recalculado
    // del lado de `Mantenimiento` (invariante 6 de CLAUDE.md).
    $this->get(route('panel.ordenes-mantenimiento.edit', $orden))
        ->assertOk()
        ->assertSee(__('mantenimiento.ordenes.detalle_precio_final'))
        ->assertSee('Bs 41.25');
});

it('cierra una orden sumando el costo de varias líneas de repuestos en un único gasto', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenesMantenimiento('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenesMantenimiento($encargado, $idRol);

    $dron = Dron::query()->create(['identificador' => 'DRN-001']);
    $repuestoA = Repuesto::query()->create(['codigo' => 'REP-A', 'descripcion' => 'Hélice', 'unidad' => 'unidad', 'costo_unitario' => '10.00']);
    $repuestoB = Repuesto::query()->create(['codigo' => 'REP-B', 'descripcion' => 'Motor', 'unidad' => 'unidad', 'costo_unitario' => '50.00']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    Stock::query()->create(['repuesto_id' => $repuestoA->id, 'base_id' => $base->id, 'cantidad' => '10.00', 'stock_minimo' => '0']);
    Stock::query()->create(['repuesto_id' => $repuestoB->id, 'base_id' => $base->id, 'cantidad' => '10.00', 'stock_minimo' => '0']);

    $this->post(route('panel.ordenes-mantenimiento.store'), payloadOrdenMantenimiento(['equipo_id' => (string) $dron->id]));
    $orden = OrdenMantenimiento::query()->sole();

    $this->post(route('panel.ordenes-mantenimiento.cerrar', $orden), [
        'repuestos' => [
            ['repuesto_id' => (string) $repuestoA->id, 'base_id' => (string) $base->id, 'cantidad' => '2.00'],
            ['repuesto_id' => (string) $repuestoB->id, 'base_id' => (string) $base->id, 'cantidad' => '1.00'],
        ],
        'descripcion_final' => 'Se cambiaron la hélice y el motor.',
    ])->assertRedirect(route('panel.ordenes-mantenimiento.index'));

    // 2 × 10.00 + 1 × 50.00 = 70.00
    $gasto = Gasto::query()->sole();
    expect($gasto->monto)->toBe('70.00')
        ->and(MovimientoStock::query()->count())->toBe(2);
});

it('rechaza el cierre sin stock suficiente: no descuenta nada y no crea ningún gasto', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenesMantenimiento('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenesMantenimiento($encargado, $idRol);

    $dron = Dron::query()->create(['identificador' => 'DRN-001']);
    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad', 'costo_unitario' => '8.25']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '3.00', 'stock_minimo' => '0']);

    $this->post(route('panel.ordenes-mantenimiento.store'), payloadOrdenMantenimiento(['equipo_id' => (string) $dron->id]));
    $orden = OrdenMantenimiento::query()->sole();

    $this->postJson(route('panel.ordenes-mantenimiento.cerrar', $orden), [
        'repuestos' => [
            ['repuesto_id' => (string) $repuesto->id, 'base_id' => (string) $base->id, 'cantidad' => '9.00'],
        ],
        'descripcion_final' => 'Se intentó cambiar la hélice.',
    ])->assertStatus(422);

    $stock = Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $base->id)->sole();
    expect($stock->cantidad)->toBe('3.00')
        ->and(MovimientoStock::query()->count())->toBe(0)
        ->and(Gasto::query()->count())->toBe(0);

    $orden->refresh();
    expect($orden->estado->value)->toBe('abierta')
        ->and($orden->gasto_id)->toBeNull();
});

it('rechaza cerrar una orden que ya está cerrada: transición inválida', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenesMantenimiento('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenesMantenimiento($encargado, $idRol);

    $dron = Dron::query()->create(['identificador' => 'DRN-001']);
    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad', 'costo_unitario' => '8.25']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '20.00', 'stock_minimo' => '0']);

    $this->post(route('panel.ordenes-mantenimiento.store'), payloadOrdenMantenimiento(['equipo_id' => (string) $dron->id]));
    $orden = OrdenMantenimiento::query()->sole();

    $lineas = [
        'repuestos' => [['repuesto_id' => (string) $repuesto->id, 'base_id' => (string) $base->id, 'cantidad' => '5.00']],
        'descripcion_final' => 'Se cambió la hélice dañada.',
    ];

    $this->post(route('panel.ordenes-mantenimiento.cerrar', $orden), $lineas)
        ->assertRedirect(route('panel.ordenes-mantenimiento.index'));

    expect(Gasto::query()->count())->toBe(1);

    $this->postJson(route('panel.ordenes-mantenimiento.cerrar', $orden), $lineas)
        ->assertStatus(422);

    // El segundo intento no descontó stock de nuevo ni generó un segundo gasto.
    expect(MovimientoStock::query()->count())->toBe(1)
        ->and(Gasto::query()->count())->toBe(1);
});

it('rechaza cerrar sin descripción final (HU-89, tarea 104)', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenesMantenimiento('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenesMantenimiento($encargado, $idRol);

    $dron = Dron::query()->create(['identificador' => 'DRN-001']);
    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad', 'costo_unitario' => '8.25']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '20.00', 'stock_minimo' => '0']);

    $this->post(route('panel.ordenes-mantenimiento.store'), payloadOrdenMantenimiento(['equipo_id' => (string) $dron->id]));
    $orden = OrdenMantenimiento::query()->sole();

    $this->postJson(route('panel.ordenes-mantenimiento.cerrar', $orden), [
        'repuestos' => [['repuesto_id' => (string) $repuesto->id, 'base_id' => (string) $base->id, 'cantidad' => '5.00']],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('descripcion_final');

    $orden->refresh();
    expect($orden->estado->value)->toBe('abierta')
        ->and($orden->descripcion_final)->toBeNull()
        ->and(Gasto::query()->count())->toBe(0);
});

it('registra en bitácora la apertura y el cierre de una orden', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenesMantenimiento('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenesMantenimiento($encargado, $idRol);

    $dron = Dron::query()->create(['identificador' => 'DRN-001']);
    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad', 'costo_unitario' => '8.25']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '20.00', 'stock_minimo' => '0']);

    $this->post(route('panel.ordenes-mantenimiento.store'), payloadOrdenMantenimiento(['equipo_id' => (string) $dron->id]));
    $orden = OrdenMantenimiento::query()->sole();

    $filaCreada = Bitacora::query()
        ->where('tabla', 'man_ordenes_mantenimiento')
        ->where('registro_id', $orden->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();
    expect($filaCreada->user_id)->toBe($encargado->id);

    $this->post(route('panel.ordenes-mantenimiento.cerrar', $orden), [
        'repuestos' => [['repuesto_id' => (string) $repuesto->id, 'base_id' => (string) $base->id, 'cantidad' => '5.00']],
        'descripcion_final' => 'Se cambió la hélice dañada.',
    ])->assertRedirect(route('panel.ordenes-mantenimiento.index'));

    $filaCerrada = Bitacora::query()
        ->where('tabla', 'man_ordenes_mantenimiento')
        ->where('registro_id', $orden->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();
    expect($filaCerrada->user_id)->toBe($encargado->id)
        ->and($filaCerrada->despues['estado'])->toBe('cerrada');
});

it('un rol sin el permiso recibe 403 en todas las acciones de órdenes de mantenimiento', function () {
    [$piloto, $idRol] = usuarioConRolParaOrdenesMantenimiento('piloto.curioso', 'piloto');
    entrarAlPanelParaOrdenesMantenimiento($piloto, $idRol);

    $dron = Dron::query()->create(['identificador' => 'DRN-001']);
    $orden = OrdenMantenimiento::query()->create([
        'equipo_tipo' => 'dron',
        'equipo_id' => $dron->id,
        'tipo' => 'preventivo',
        'descripcion' => 'Revisión de rutina',
        'estado' => 'abierta',
        'fecha_apertura' => now(),
    ]);
    $repuesto = Repuesto::query()->create(['codigo' => 'REP-001', 'descripcion' => 'Hélice', 'unidad' => 'unidad']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);

    $this->get(route('panel.ordenes-mantenimiento.index'))->assertForbidden();
    $this->get(route('panel.ordenes-mantenimiento.create'))->assertForbidden();
    $this->post(route('panel.ordenes-mantenimiento.store'), payloadOrdenMantenimiento(['equipo_id' => (string) $dron->id]))->assertForbidden();
    $this->get(route('panel.ordenes-mantenimiento.edit', $orden))->assertForbidden();
    $this->post(route('panel.ordenes-mantenimiento.cerrar', $orden), [
        'repuestos' => [['repuesto_id' => (string) $repuesto->id, 'base_id' => (string) $base->id, 'cantidad' => '1']],
        'descripcion_final' => 'Se cambió la hélice dañada.',
    ])->assertForbidden();

    expect(OrdenMantenimiento::query()->count())->toBe(1)
        ->and($orden->fresh()->estado->value)->toBe('abierta');
});

it('publica el ítem de menú de órdenes de mantenimiento, gateado por su permiso de ver', function () {
    $item = SecMenu::query()
        ->where('label', 'menu.mantenimiento.items.ordenes')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'mantenimiento.orden.ver')
        ->value('id');

    expect($item->ruta)->toBe('panel.ordenes-mantenimiento.index')
        ->and($item->permission_id)->toBe($idPermiso);
});
