<?php

use Illuminate\Support\Facades\Blade;

/*
 * Átomo `checkbox-group` (resources/views/components/atoms/checkbox-group.blade.php,
 * tarea 76 / HU-53, etapa 3). Esta suite fija el contrato ESTÁTICO de
 * marcado: cada casilla es un `<input type="checkbox">` real (el teclado
 * entre ellas es nativo, sin JS que probar ahí), el umbral de búsqueda
 * automática, y el reenvío de $attributes (LSP).
 *
 * El filtro de texto en sí (ocultar/mostrar filas al tipear) sí necesita un
 * navegador real — eso lo cubre tests/Visual/checkbox-group-atom.spec.ts,
 * corrido a mano (ver su cabecera).
 */

const REPUESTOS_CORTOS = [
    'boquilla' => 'Boquilla',
    'filtro' => 'Filtro',
    'bateria' => 'Batería',
];

const REPUESTOS_LARGOS = [
    'repuesto-1' => 'Repuesto 1',
    'repuesto-2' => 'Repuesto 2',
    'repuesto-3' => 'Repuesto 3',
    'repuesto-4' => 'Repuesto 4',
    'repuesto-5' => 'Repuesto 5',
    'repuesto-6' => 'Repuesto 6',
    'repuesto-7' => 'Repuesto 7',
    'repuesto-8' => 'Repuesto 8',
    'repuesto-9' => 'Repuesto 9',
];

test('cada opción es un checkbox real con name[] y su value propio', function () {
    $html = Blade::render(
        '<x-atoms.checkbox-group name="repuestos" :options="$opciones" />',
        ['opciones' => REPUESTOS_CORTOS]
    );

    expect($html)
        ->toContain('type="checkbox"')
        ->toContain('name="repuestos[]"')
        ->toContain('value="boquilla"')
        ->toContain('value="filtro"')
        ->toContain('value="bateria"');
});

test('value marca las casillas correspondientes', function () {
    $html = Blade::render(
        '<x-atoms.checkbox-group name="repuestos" :options="$opciones" :value="$valor" />',
        ['opciones' => REPUESTOS_CORTOS, 'valor' => ['boquilla', 'bateria']]
    );

    expect($html)
        ->toMatch('/value="boquilla"\s+checked/')
        ->toMatch('/value="bateria"\s+checked/')
        ->not->toMatch('/value="filtro"\s+checked/');
});

test('con 8 opciones o menos no arma la búsqueda', function () {
    $html = Blade::render(
        '<x-atoms.checkbox-group name="repuestos" :options="$opciones" />',
        ['opciones' => REPUESTOS_CORTOS]
    );

    expect($html)->not->toContain('data-ag-checkbox-group-search');
});

test('con más de 8 opciones arma la búsqueda, oculta de fábrica', function () {
    $html = Blade::render(
        '<x-atoms.checkbox-group name="repuestos" :options="$opciones" />',
        ['opciones' => REPUESTOS_LARGOS + ['repuesto-10' => 'Repuesto 10']]
    );

    expect($html)->toMatch('/<div[^>]*data-ag-checkbox-group-search-wrap[^>]*hidden[^>]*>/');
});

test('label se renderiza como legend, con required y su asterisco', function () {
    $html = Blade::render(
        '<x-atoms.checkbox-group name="repuestos" label="Repuestos usados" :options="$opciones" required />',
        ['opciones' => REPUESTOS_CORTOS]
    );

    expect($html)
        ->toContain('<legend')
        ->toContain('Repuestos usados')
        ->toContain('ag-checkbox-group__required')
        ->toContain('aria-required="true"');
});

test('help y error arman aria-describedby en el fieldset', function () {
    $html = Blade::render(
        '<x-atoms.checkbox-group name="repuestos" id="campo-repuestos" :options="$opciones" help="Ayuda" error="Requerido" />',
        ['opciones' => REPUESTOS_CORTOS]
    );

    expect($html)
        ->toContain('id="campo-repuestos-help" class="ag-checkbox-group__help">Ayuda</p>')
        ->toContain('role="alert"')
        ->toContain('id="campo-repuestos-error" class="ag-checkbox-group__error"')
        ->toContain('aria-describedby="campo-repuestos-help campo-repuestos-error"');
});

test('disabled se propaga a todas las casillas', function () {
    $html = Blade::render(
        '<x-atoms.checkbox-group name="repuestos" :options="$opciones" disabled />',
        ['opciones' => REPUESTOS_CORTOS]
    );

    expect(substr_count($html, 'disabled'))->toBe(3);
});

test('$attributes: la class extra va a la raíz (fieldset), el resto (wire:model) a cada casilla', function () {
    $html = Blade::render(
        '<x-atoms.checkbox-group name="repuestos" :options="$opciones" class="ag-form-section__field--full" wire:model="repuestosSeleccionados" />',
        ['opciones' => REPUESTOS_CORTOS]
    );

    expect($html)->toMatch('/<fieldset[^>]*class="ag-checkbox-group ag-form-section__field--full"/');
    expect(substr_count($html, 'wire:model="repuestosSeleccionados"'))->toBe(3);
});
