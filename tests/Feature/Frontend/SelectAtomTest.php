<?php

use Illuminate\Support\Facades\Blade;

/*
 * Átomo `select` (resources/views/components/atoms/select.blade.php,
 * tarea 76 / HU-53). Esta suite fija el contrato ESTÁTICO de marcado: el
 * fallback nativo sin JS, el andamiaje ARIA con el que arranca la página
 * (antes de que resources/js/atoms/select.js corra) y el reenvío de
 * $attributes (LSP, docs/diseno/guia_pantalla_panel.md §3).
 *
 * El flujo de teclado real (abrir/navegar/elegir, aria-activedescendant
 * cambiando en vivo) necesita un navegador de verdad — eso lo cubre
 * tests/Visual/select-atom.spec.ts, corrido a mano (ver su cabecera).
 */

const OPCIONES_CORTAS = [
    'administrador' => 'Administrador',
    'piloto' => 'Piloto',
    'jefe_campo' => 'Jefe de campo',
];

const OPCIONES_LARGAS = [
    'lote-1' => 'Lote 1',
    'lote-2' => 'Lote 2',
    'lote-3' => 'Lote 3',
    'lote-4' => 'Lote 4',
    'lote-5' => 'Lote 5',
    'lote-6' => 'Lote 6',
    'lote-7' => 'Lote 7',
    'lote-8' => 'Lote 8',
    'lote-9' => 'Lote 9',
];

test('el <select> nativo lleva name, id y las opciones con su valor seleccionado', function () {
    $html = Blade::render(
        '<x-atoms.select name="rol" :options="$opciones" value="piloto" />',
        ['opciones' => OPCIONES_CORTAS]
    );

    expect($html)
        ->toContain('name="rol"')
        ->toContain('id="rol"')
        ->toContain('<option value="piloto" selected')
        ->toContain('>Piloto<')
        ->toContain('<option value="administrador"')
        ->not->toContain('<option value="administrador" selected');
});

test('sin JS el nativo es el único control visible: el trigger arranca hidden', function () {
    $html = Blade::render(
        '<x-atoms.select name="rol" :options="$opciones" />',
        ['opciones' => OPCIONES_CORTAS]
    );

    expect($html)->toMatch('/<div[^>]*data-ag-select-trigger[^>]*hidden[^>]*>/');
});

test('placeholder se renderiza como opción deshabilitada y oculta', function () {
    $html = Blade::render(
        '<x-atoms.select name="rol" :options="$opciones" placeholder="Elegí un rol" />',
        ['opciones' => OPCIONES_CORTAS]
    );

    expect($html)->toContain('<option value="" selected disabled hidden>Elegí un rol</option>');
});

test('label, required y el asterisco se propagan, y el for apunta al id del select', function () {
    $html = Blade::render(
        '<x-atoms.select name="rol" id="campo-rol" label="Rol" :options="$opciones" required />',
        ['opciones' => OPCIONES_CORTAS]
    );

    expect($html)
        ->toContain('for="campo-rol"')
        ->toContain('id="campo-rol"')
        ->toContain('Rol')
        ->toContain('ag-select__required')
        ->toContain('required');
});

test('help y error arman aria-describedby en el nativo y en el trigger', function () {
    $html = Blade::render(
        '<x-atoms.select name="rol" :options="$opciones" help="Ayuda" error="Requerido" />',
        ['opciones' => OPCIONES_CORTAS]
    );

    expect($html)
        ->toContain('id="rol-help" class="ag-select__help">Ayuda</p>')
        ->toContain('role="alert"')
        ->toContain('id="rol-error" class="ag-select__error"')
        ->toContain('Requerido</p>')
        ->toContain('aria-describedby="rol-help rol-error"');
});

test('disabled se propaga al nativo y al trigger (aria-disabled, tabindex -1)', function () {
    $html = Blade::render(
        '<x-atoms.select name="rol" :options="$opciones" disabled />',
        ['opciones' => OPCIONES_CORTAS]
    );

    expect($html)
        ->toContain('disabled')
        ->toContain('aria-disabled="true"')
        ->toMatch('/data-ag-select-trigger[^>]*tabindex="-1"/');
});

test('el andamiaje ARIA del combobox arranca cerrado y sin aria-activedescendant', function () {
    $html = Blade::render(
        '<x-atoms.select name="rol" id="campo-rol" :options="$opciones" />',
        ['opciones' => OPCIONES_CORTAS]
    );

    expect($html)
        ->toContain('role="combobox"')
        ->toContain('aria-haspopup="listbox"')
        ->toContain('aria-expanded="false"')
        ->toContain('aria-controls="campo-rol-listbox"')
        ->toContain('id="campo-rol-listbox"')
        ->toContain('role="listbox"')
        ->not->toContain('aria-activedescendant');
});

test('con 8 opciones o menos no se marca como buscable', function () {
    $html = Blade::render(
        '<x-atoms.select name="rol" :options="$opciones" />',
        ['opciones' => OPCIONES_CORTAS]
    );

    expect($html)->toContain('data-ag-select-buscable="0"');
});

test('con más de 8 opciones se marca como buscable', function () {
    $html = Blade::render(
        '<x-atoms.select name="lote_id" :options="$opciones" />',
        ['opciones' => OPCIONES_LARGAS + ['lote-10' => 'Lote 10']]
    );

    expect($html)->toContain('data-ag-select-buscable="1"');
});

test('$attributes: la class extra va a la raíz, el resto (data-*, wire:model) al nativo', function () {
    $html = Blade::render(
        '<x-atoms.select name="rol" :options="$opciones" class="ag-form-section__field--full" data-ag-contrato-rol wire:model="rol" />',
        ['opciones' => OPCIONES_CORTAS]
    );

    expect($html)
        ->toMatch('/<div[^>]*class="ag-select ag-form-section__field--full"/')
        ->toMatch('/<select[^>]*data-ag-contrato-rol/')
        ->toMatch('/<select[^>]*wire:model="rol"/');
});
