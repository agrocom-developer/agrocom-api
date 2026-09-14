<?php

use Illuminate\Support\Facades\Blade;

/*
 * Átomo `date` (resources/views/components/atoms/date.blade.php, tarea 76 /
 * HU-53, etapa 2). Esta suite fija el contrato ESTÁTICO de marcado: el
 * fallback nativo sin JS, el andamiaje ARIA con el que arranca la página
 * (antes de que resources/js/atoms/date.js corra) y el reenvío de
 * $attributes (LSP, docs/diseno/guia_pantalla_panel.md §3).
 *
 * El flujo de teclado real sobre la grilla del calendario (abrir el diálogo,
 * navegar con flechas/PageUp/PageDown, elegir, Escape, min/max) necesita un
 * navegador de verdad — no hay spec automatizado para eso (ver el skill
 * `verificacion`, "Qué NO cubre la cascada"); se revisa a mano en el
 * navegador cuando cambia este átomo.
 */

test('el <input type="date"> nativo lleva name, id, value, min y max', function () {
    $html = Blade::render(
        '<x-atoms.date name="fecha_inicio" value="2026-09-08" min="2026-01-01" max="2026-12-31" />'
    );

    expect($html)
        ->toContain('type="date"')
        ->toContain('name="fecha_inicio"')
        ->toContain('id="fecha_inicio"')
        ->toContain('value="2026-09-08"')
        ->toContain('min="2026-01-01"')
        ->toContain('max="2026-12-31"');
});

test('sin JS el nativo es el único control visible: el disparador arranca hidden', function () {
    $html = Blade::render('<x-atoms.date name="fecha_inicio" />');

    expect($html)->toMatch('/<button[^>]*data-ag-date-trigger[^>]*hidden[^>]*>/');
});

test('con value, el disparador muestra la fecha en d\\/m\\/Y aunque JS no haya corrido', function () {
    $html = Blade::render('<x-atoms.date name="fecha_inicio" value="2026-09-08" />');

    expect($html)->toContain('<span class="ag-date__value" data-ag-date-value>08/09/2026</span>');
});

test('sin value, el disparador muestra el placeholder', function () {
    $html = Blade::render('<x-atoms.date name="fecha_inicio" placeholder="Elegí una fecha" />');

    expect($html)->toContain('<span class="ag-date__value" data-ag-date-value>Elegí una fecha</span>');
});

test('label, required y el asterisco se propagan, y el for apunta al id del campo', function () {
    $html = Blade::render(
        '<x-atoms.date name="fecha_inicio" id="campo-fecha" label="Fecha de inicio" required />'
    );

    expect($html)
        ->toContain('for="campo-fecha"')
        ->toContain('id="campo-fecha"')
        ->toContain('Fecha de inicio')
        ->toContain('ag-date__required')
        ->toContain('required');
});

test('help y error arman aria-describedby en el nativo y en el disparador', function () {
    $html = Blade::render('<x-atoms.date name="fecha_inicio" help="Ayuda" error="Requerido" />');

    expect($html)
        ->toContain('id="fecha_inicio-help" class="ag-date__help">Ayuda</p>')
        ->toContain('role="alert"')
        ->toContain('id="fecha_inicio-error" class="ag-date__error"')
        ->toContain('Requerido</p>')
        ->toMatch('/aria-describedby="fecha_inicio-help fecha_inicio-error"/');
});

test('disabled se propaga al nativo y al disparador', function () {
    $html = Blade::render('<x-atoms.date name="fecha_inicio" disabled />');

    expect($html)
        ->toMatch('/<input[^>]*disabled[^>]*class="ag-date__native"/')
        ->toMatch('/<button[^>]*data-ag-date-trigger[^>]*disabled[^>]*hidden/');
});

test('el diálogo arranca oculto, es un role=dialog modal y expone aria-label', function () {
    $html = Blade::render('<x-atoms.date name="fecha_inicio" label="Fecha de inicio" />');

    expect($html)
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('aria-label="Fecha de inicio"')
        ->toMatch('/<div[^>]*data-ag-date-dialog[^>]*hidden[^>]*>/');
});

test('el diálogo cae al placeholder ui.date.elegir_fecha cuando no hay label', function () {
    $html = Blade::render('<x-atoms.date name="fecha_inicio" />');

    expect($html)->toContain('aria-label="Elegir fecha"');
});

test('el botón de limpiar es hermano del disparador, no hijo (HTML válido)', function () {
    $html = Blade::render('<x-atoms.date name="fecha_inicio" value="2026-09-08" />');

    $inicioTrigger = mb_strpos($html, 'data-ag-date-trigger');
    $cierreTrigger = mb_strpos($html, '</button>', $inicioTrigger);
    $fragmentoTrigger = mb_substr($html, $inicioTrigger, $cierreTrigger - $inicioTrigger);

    expect($fragmentoTrigger)->not->toContain('data-ag-date-clear');
    expect($html)->toContain('data-ag-date-clear');
});

test('$attributes: la class extra va a la raíz, el resto (data-*, wire:model) al nativo', function () {
    $html = Blade::render(
        '<x-atoms.date name="fecha_inicio" class="ag-form-section__field--full" data-ag-contrato-fecha wire:model="fecha" />'
    );

    expect($html)
        ->toMatch('/<div[^>]*class="ag-date ag-form-section__field--full"/')
        ->toMatch('/<input[^>]*data-ag-contrato-fecha/')
        ->toMatch('/<input[^>]*wire:model="fecha"/');
});
