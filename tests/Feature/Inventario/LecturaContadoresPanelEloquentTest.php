<?php

use App\Dominios\Inventario\Contratos\LecturaContadoresPanel;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Contrato de lectura `Inventario\Contratos\LecturaContadoresPanel` (TE-14,
 * tarea 60): el contador real que alimenta el badge de stock bajo mínimo.
 * Test "unitario" en el sentido de probar la clase directo (sin HTTP) —
 * mismo criterio que `Operaciones\LecturaContadoresPanelEloquentTest`.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

it('stockBajoMinimo cuenta las filas con cantidad <= stock_minimo, no las que están por encima', function () {
    $repuesto = Repuesto::create(['codigo' => 'REP-CTP-1', 'descripcion' => 'Repuesto ctp 1', 'unidad' => 'unidad']);
    $base = PerBase::create(['nombre' => 'Base ctp']);

    Stock::create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '4.00', 'stock_minimo' => '20.00']);

    $repuesto2 = Repuesto::create(['codigo' => 'REP-CTP-2', 'descripcion' => 'Repuesto ctp 2', 'unidad' => 'unidad']);
    Stock::create(['repuesto_id' => $repuesto2->id, 'base_id' => $base->id, 'cantidad' => '2.00', 'stock_minimo' => '2.00']);

    $repuesto3 = Repuesto::create(['codigo' => 'REP-CTP-3', 'descripcion' => 'Repuesto ctp 3', 'unidad' => 'unidad']);
    Stock::create(['repuesto_id' => $repuesto3->id, 'base_id' => $base->id, 'cantidad' => '50.00', 'stock_minimo' => '5.00']);

    expect(app(LecturaContadoresPanel::class)->stockBajoMinimo())->toBe(2);
});

it('sin filas de stock, el contador es cero', function () {
    expect(app(LecturaContadoresPanel::class)->stockBajoMinimo())->toBe(0);
});
