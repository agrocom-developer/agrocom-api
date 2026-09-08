<?php

use Illuminate\Support\Facades\Blade;

/*
 * Átomo `radio-group` (resources/views/components/atoms/radio-group.blade.php,
 * tarea 76 / HU-53, etapa 3). Selección única con `<input type="radio">`
 * reales agrupados por `name`: el teclado (flechas entre radios del mismo
 * grupo) es nativo del navegador, sin JS propio que probar — el contrato
 * ESTÁTICO de marcado alcanza.
 */

const OPCIONES_TIPO_APLICACION = [
    'terrestre' => 'Terrestre',
    'aerea' => 'Aérea',
];

test('cada opción es un radio real con el mismo name y su value propio', function () {
    $html = Blade::render(
        '<x-atoms.radio-group name="tipo_aplicacion" :options="$opciones" />',
        ['opciones' => OPCIONES_TIPO_APLICACION]
    );

    expect($html)
        ->toContain('type="radio"')
        ->toContain('name="tipo_aplicacion"')
        ->toContain('value="terrestre"')
        ->toContain('value="aerea"');
});

test('value marca el radio correspondiente, y solo ese', function () {
    $html = Blade::render(
        '<x-atoms.radio-group name="tipo_aplicacion" :options="$opciones" value="aerea" />',
        ['opciones' => OPCIONES_TIPO_APLICACION]
    );

    expect($html)
        ->toMatch('/value="aerea"\s+checked/')
        ->not->toMatch('/value="terrestre"\s+checked/');
});

test('label se renderiza como legend, con required y su asterisco', function () {
    $html = Blade::render(
        '<x-atoms.radio-group name="tipo_aplicacion" label="Tipo de aplicación" :options="$opciones" required />',
        ['opciones' => OPCIONES_TIPO_APLICACION]
    );

    expect($html)
        ->toContain('<legend')
        ->toContain('Tipo de aplicación')
        ->toContain('ag-radio-group__required')
        ->toMatch('/<input[^>]*required[^>]*>/');
});

test('help y error arman aria-describedby en el fieldset', function () {
    $html = Blade::render(
        '<x-atoms.radio-group name="tipo_aplicacion" id="campo-tipo" :options="$opciones" help="Ayuda" error="Requerido" />',
        ['opciones' => OPCIONES_TIPO_APLICACION]
    );

    expect($html)
        ->toContain('id="campo-tipo-help" class="ag-radio-group__help">Ayuda</p>')
        ->toContain('role="alert"')
        ->toContain('id="campo-tipo-error" class="ag-radio-group__error"')
        ->toContain('aria-describedby="campo-tipo-help campo-tipo-error"');
});

test('disabled se propaga a todos los radios', function () {
    $html = Blade::render(
        '<x-atoms.radio-group name="tipo_aplicacion" :options="$opciones" disabled />',
        ['opciones' => OPCIONES_TIPO_APLICACION]
    );

    expect(substr_count($html, 'disabled'))->toBe(2);
});

test('$attributes: la class extra va a la raíz (fieldset), el resto (wire:model) a cada radio', function () {
    $html = Blade::render(
        '<x-atoms.radio-group name="tipo_aplicacion" :options="$opciones" class="ag-form-section__field--full" wire:model="tipoAplicacion" />',
        ['opciones' => OPCIONES_TIPO_APLICACION]
    );

    expect($html)->toMatch('/<fieldset[^>]*class="ag-radio-group ag-form-section__field--full"/');
    expect(substr_count($html, 'wire:model="tipoAplicacion"'))->toBe(2);
});
