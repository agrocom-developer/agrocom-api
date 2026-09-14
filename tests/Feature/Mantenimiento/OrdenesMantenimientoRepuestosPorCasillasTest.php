<?php

use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use App\Dominios\Inventario\Infraestructura\Eloquent\MovimientoStock;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-57 (tarea 80): selector de repuestos por casillas en el cierre de una
 * orden de mantenimiento, sobre lo que dejó HU-37 (tarea 53,
 * OrdenesMantenimientoPanelTest.php — esa suite pega directo a la ruta de
 * cierre sin pasar por la vista, así que sigue vigente sin tocarla: no
 * prueba nada de lo que cambió acá y nada de lo que cambió acá le rompe
 * nada a ella).
 *
 * Esta suite cubre lo que sí es nuevo:
 * 1. El formulario nuevo manda `repuestos` keyed por repuesto_id (no
 *    0..n-1, como antes) — mismo resultado de cierre que un payload
 *    secuencial, probando que el `->values()` que agregó el controlador
 *    antes de tocar la máquina de estados no cambia nada (invariante: "no
 *    tocar la lógica de cierre").
 * 2. Lo que renderiza `ordenes/edit.blade.php`: casillas en vez de los dos
 *    `<select>` por línea, y el repintado de `old('repuestos')` tras un
 *    error de validación (stock insuficiente, HU-37).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaRepuestosPorCasillas(): SecUser
{
    $usuario = SecUser::factory()->create(['username' => 'encargado.casillas', 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', 'encargado_operaciones')->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRol]);

    return $usuario;
}

it('cierra una orden con `repuestos` keyed por repuesto_id (como lo arma el selector por casillas): mismo resultado que un payload secuencial', function () {
    usuarioConRolParaRepuestosPorCasillas();

    $dron = Dron::query()->create(['identificador' => 'DRN-900']);
    $repuestoA = Repuesto::query()->create(['codigo' => 'REP-A', 'descripcion' => 'Hélice', 'unidad' => 'unidad', 'costo_unitario' => '10.00']);
    $repuestoB = Repuesto::query()->create(['codigo' => 'REP-B', 'descripcion' => 'Motor', 'unidad' => 'unidad', 'costo_unitario' => '50.00']);
    $repuestoC = Repuesto::query()->create(['codigo' => 'REP-C', 'descripcion' => 'Batería', 'unidad' => 'unidad', 'costo_unitario' => '5.00']);
    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    Stock::query()->create(['repuesto_id' => $repuestoA->id, 'base_id' => $base->id, 'cantidad' => '10.00', 'stock_minimo' => '0']);
    Stock::query()->create(['repuesto_id' => $repuestoB->id, 'base_id' => $base->id, 'cantidad' => '10.00', 'stock_minimo' => '0']);
    Stock::query()->create(['repuesto_id' => $repuestoC->id, 'base_id' => $base->id, 'cantidad' => '10.00', 'stock_minimo' => '0']);

    test()->post(route('panel.ordenes-mantenimiento.store'), [
        'equipo_tipo' => 'dron',
        'equipo_id' => (string) $dron->id,
        'tipo' => 'preventivo',
        'descripcion' => 'Revisión de rutina',
    ]);
    $orden = OrdenMantenimiento::query()->sole();

    test()->post(route('panel.ordenes-mantenimiento.cerrar', $orden), [
        'base_global' => (string) $base->id,
        'repuestos' => [
            $repuestoA->id => ['repuesto_id' => (string) $repuestoA->id, 'base_id' => (string) $base->id, 'cantidad' => '2.00'],
            $repuestoB->id => ['repuesto_id' => (string) $repuestoB->id, 'base_id' => (string) $base->id, 'cantidad' => '1.00'],
            $repuestoC->id => ['repuesto_id' => (string) $repuestoC->id, 'base_id' => (string) $base->id, 'cantidad' => '3.00'],
        ],
        'descripcion_final' => 'Se cambiaron hélice, motor y batería.',
    ])->assertRedirect(route('panel.ordenes-mantenimiento.index'));

    // 2×10.00 + 1×50.00 + 3×5.00 = 85.00
    $gasto = Gasto::query()->sole();
    expect($gasto->monto)->toBe('85.00')
        ->and(MovimientoStock::query()->count())->toBe(3);

    expect(Stock::query()->where('repuesto_id', $repuestoA->id)->value('cantidad'))->toBe('8.00')
        ->and(Stock::query()->where('repuesto_id', $repuestoB->id)->value('cantidad'))->toBe('9.00')
        ->and(Stock::query()->where('repuesto_id', $repuestoC->id)->value('cantidad'))->toBe('7.00');

    $orden->refresh();
    expect($orden->estado->value)->toBe('cerrada')
        ->and($orden->gasto_id)->toBe($gasto->id)
        ->and($orden->fecha_cierre)->not->toBeNull();
});

it('el detalle de la orden pinta el selector de repuestos por casillas, con el repuesto y la base a la vista', function () {
    usuarioConRolParaRepuestosPorCasillas();

    $dron = Dron::query()->create(['identificador' => 'DRN-901']);
    $repuesto = Repuesto::query()->create(['codigo' => 'REP-X', 'descripcion' => 'Hélice', 'unidad' => 'unidad', 'costo_unitario' => '8.25']);
    $base = PerBase::query()->create(['nombre' => 'Base Sur']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '12.00', 'stock_minimo' => '0']);

    $orden = OrdenMantenimiento::query()->create([
        'equipo_tipo' => 'dron',
        'equipo_id' => $dron->id,
        'tipo' => 'preventivo',
        'descripcion' => 'Revisión',
        'estado' => 'abierta',
        'fecha_apertura' => now(),
    ]);

    $respuesta = test()->get(route('panel.ordenes-mantenimiento.edit', $orden));

    $respuesta->assertOk()
        ->assertSee('data-ag-checkbox-group', false)
        ->assertSee('name="repuestos_marcados[]"', false)
        ->assertSee('name="repuestos['.$repuesto->id.'][repuesto_id]"', false)
        ->assertSee('name="repuestos['.$repuesto->id.'][base_id]"', false)
        ->assertSee('name="repuestos['.$repuesto->id.'][cantidad]"', false)
        ->assertSee('REP-X', false)
        ->assertSee('Base Sur', false);
});

it('repinta la casilla marcada y la cantidad tras un error de validación (stock insuficiente, HU-37)', function () {
    usuarioConRolParaRepuestosPorCasillas();

    $dron = Dron::query()->create(['identificador' => 'DRN-902']);
    $repuesto = Repuesto::query()->create(['codigo' => 'REP-Y', 'descripcion' => 'Motor', 'unidad' => 'unidad', 'costo_unitario' => '8.25']);
    $base = PerBase::query()->create(['nombre' => 'Base Este']);
    Stock::query()->create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '2.00', 'stock_minimo' => '0']);

    test()->post(route('panel.ordenes-mantenimiento.store'), [
        'equipo_tipo' => 'dron',
        'equipo_id' => (string) $dron->id,
        'tipo' => 'preventivo',
        'descripcion' => 'Revisión',
    ]);
    $orden = OrdenMantenimiento::query()->sole();

    // Pide más de lo que hay: HU-37 lo rechaza en el servidor (no se toca)
    // y vuelve al detalle con `old('repuestos')`/`old('base_global')` cargados.
    test()->from(route('panel.ordenes-mantenimiento.edit', $orden))
        ->post(route('panel.ordenes-mantenimiento.cerrar', $orden), [
            'base_global' => (string) $base->id,
            'repuestos' => [
                $repuesto->id => ['repuesto_id' => (string) $repuesto->id, 'base_id' => (string) $base->id, 'cantidad' => '9.00'],
            ],
            'descripcion_final' => 'Se intentó cambiar el motor.',
        ])->assertRedirect(route('panel.ordenes-mantenimiento.edit', $orden));

    expect(Gasto::query()->count())->toBe(0)
        ->and(Stock::query()->where('repuesto_id', $repuesto->id)->value('cantidad'))->toBe('2.00');

    $html = test()->get(route('panel.ordenes-mantenimiento.edit', $orden))->assertOk()->getContent();

    expect($html)
        ->toMatch('/value="'.$repuesto->id.'"\s+checked/')
        ->toMatch('/name="repuestos\['.$repuesto->id.'\]\[cantidad\]"[^>]*value="9\.00"/')
        // Bug real encontrado en esta misma tarea: la línea repintada debe
        // nacer HABILITADA (server-side, no solo por JS) — si no, un
        // reenvío sin tocar nada perdería la línea entera (un campo
        // disabled no viaja en el POST).
        ->not->toMatch('/name="repuestos\['.$repuesto->id.'\]\[repuesto_id\]"[^>]*disabled/')
        ->not->toMatch('/name="repuestos\['.$repuesto->id.'\]\[base_id\]"[^>]*disabled/')
        ->not->toMatch('/name="repuestos\['.$repuesto->id.'\]\[cantidad\]"[^>]*disabled/');
});
