<?php

use Illuminate\Support\Facades\Blade;

/*
 * Átomo `textarea` (resources/views/components/atoms/textarea.blade.php,
 * tarea 76 / HU-53, etapa 3). Mismo contrato que `atoms/input`, sin JS
 * propio — el contrato ESTÁTICO de marcado alcanza para probarlo entero.
 */

test('el <textarea> lleva name, id, rows y el value como contenido', function () {
    $html = Blade::render('<x-atoms.textarea name="observaciones" value="Nota inicial" />');

    expect($html)
        ->toContain('name="observaciones"')
        ->toContain('id="observaciones"')
        ->toContain('rows="4"')
        ->toContain('>Nota inicial</textarea>');
});

test('rows es configurable', function () {
    $html = Blade::render('<x-atoms.textarea name="observaciones" :rows="8" />');

    expect($html)->toContain('rows="8"');
});

test('placeholder, label, required y el asterisco se propagan, y el for apunta al id', function () {
    $html = Blade::render(
        '<x-atoms.textarea name="obs" id="campo-obs" label="Observaciones" placeholder="Escribí acá" required />'
    );

    expect($html)
        ->toContain('for="campo-obs"')
        ->toContain('id="campo-obs"')
        ->toContain('Observaciones')
        ->toContain('placeholder="Escribí acá"')
        ->toContain('ag-textarea__required')
        ->toMatch('/<textarea[^>]*required[^>]*>/');
});

test('help y error arman aria-describedby y la clase de control con error', function () {
    $html = Blade::render('<x-atoms.textarea name="obs" help="Ayuda" error="Requerido" />');

    expect($html)
        ->toContain('id="obs-help" class="ag-textarea__help">Ayuda</p>')
        ->toContain('role="alert"')
        ->toContain('id="obs-error" class="ag-textarea__error"')
        ->toContain('Requerido</p>')
        ->toContain('aria-describedby="obs-help obs-error"')
        ->toContain('ag-textarea__control--error');
});

test('disabled se propaga al control real', function () {
    $html = Blade::render('<x-atoms.textarea name="obs" disabled />');

    expect($html)->toMatch('/<textarea[^>]*disabled[^>]*>/');
});

test('$attributes: la class extra va a la raíz, el resto (data-*, wire:model) al textarea real', function () {
    $html = Blade::render(
        '<x-atoms.textarea name="obs" class="ag-form-section__field--full" data-ag-contrato-obs wire:model="obs" />'
    );

    expect($html)
        ->toMatch('/<div[^>]*class="ag-textarea ag-form-section__field--full"/')
        ->toMatch('/<textarea[^>]*data-ag-contrato-obs/')
        ->toMatch('/<textarea[^>]*wire:model="obs"/');
});
