<?php

use Illuminate\Support\Facades\Blade;

/*
 * Átomo `checkbox` (resources/views/components/atoms/checkbox.blade.php,
 * tarea 76 / HU-53, etapa 3). Sin JS propio (el estado visual lo resuelve
 * CSS a partir de `:checked`, igual que `atoms/switch`), así que el
 * contrato ESTÁTICO de marcado alcanza para probarlo entero: no hace falta
 * un spec de Playwright.
 */

test('el <input type="checkbox"> lleva name, id y value con el default de Laravel', function () {
    $html = Blade::render('<x-atoms.checkbox name="acepta_terminos" />');

    expect($html)
        ->toContain('type="checkbox"')
        ->toContain('name="acepta_terminos"')
        ->toContain('id="acepta_terminos"')
        ->toContain('value="1"');
});

test('checked marca el atributo checked', function () {
    $html = Blade::render('<x-atoms.checkbox name="activo" checked />');

    expect($html)->toContain('checked');
});

test('sin checked no se marca', function () {
    $html = Blade::render('<x-atoms.checkbox name="activo" />');

    expect($html)->not->toContain('checked');
});

test('label, required y el asterisco se propagan, y el for apunta al id', function () {
    $html = Blade::render('<x-atoms.checkbox name="acepta" id="campo-acepta" label="Acepto los términos" required />');

    expect($html)
        ->toContain('for="campo-acepta"')
        ->toContain('id="campo-acepta"')
        ->toContain('Acepto los términos')
        ->toContain('ag-checkbox__required')
        ->toContain('required');
});

test('help y error arman aria-describedby', function () {
    $html = Blade::render('<x-atoms.checkbox name="acepta" help="Ayuda" error="Requerido" />');

    expect($html)
        ->toContain('id="acepta-help" class="ag-checkbox__help">Ayuda</p>')
        ->toContain('role="alert"')
        ->toContain('id="acepta-error" class="ag-checkbox__error"')
        ->toContain('Requerido</p>')
        ->toContain('aria-describedby="acepta-help acepta-error"');
});

test('disabled se propaga al input real', function () {
    $html = Blade::render('<x-atoms.checkbox name="activo" disabled />');

    expect($html)->toMatch('/<input[^>]*disabled[^>]*>/');
});

test('lleva el icono check de Material Symbols para el estado marcado', function () {
    $html = Blade::render('<x-atoms.checkbox name="activo" />');

    expect($html)->toContain('ag-checkbox__check');
});

test('$attributes: la class extra va a la raíz, el resto (data-*, wire:model) al input real', function () {
    $html = Blade::render(
        '<x-atoms.checkbox name="activo" class="ag-form-section__field--full" data-ag-contrato-activo wire:model="activo" />'
    );

    expect($html)
        ->toMatch('/<div[^>]*class="ag-checkbox ag-form-section__field--full"/')
        ->toMatch('/<input[^>]*data-ag-contrato-activo/')
        ->toMatch('/<input[^>]*wire:model="activo"/');
});
