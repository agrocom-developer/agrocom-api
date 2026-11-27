<?php

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/*
 * molecules/grafico: la gráfica siempre trae su alternativa en tabla con los mismos números, el
 * JSON que lee Chart.js solo lleva lo que se grafica, y sin datos muestra el estado vacío.
 */

uses(TestCase::class);

function graficoRenderizado(array $series, array $etiquetas = ['Uno', 'Dos']): string
{
    return Blade::render(
        '<x-molecules.grafico titulo="Ventas <b>" categoria="Mes" :etiquetas="$etiquetas" :series="$series" :formato="[\'decimales\' => 2, \'sufijo\' => \'ha\']" :vacio="\'Nada que mostrar\'" />',
        ['etiquetas' => $etiquetas, 'series' => $series],
    );
}

test('con datos dibuja el lienzo accesible y la tabla con los mismos valores', function () {
    $html = graficoRenderizado([['nombre' => 'Hectáreas', 'valores' => [1234.5, null], 'color' => 'success']]);

    expect($html)->toContain('data-ag-grafico=')
        ->and($html)->toContain('<canvas')
        ->and($html)->toContain('role="img"')
        ->and($html)->toContain('aria-describedby=')
        ->and($html)->toContain('1.234,50 ha')
        ->and($html)->toContain('ui.grafico.sin_dato')
        ->and($html)->toContain('&quot;color&quot;:&quot;success&quot;')
        ->and($html)->toContain('Ventas &lt;b&gt;')
        ->and($html)->not->toContain('Ventas <b>');
});

test('sin valores muestra el estado vacío y no carga la gráfica', function () {
    $html = graficoRenderizado([['nombre' => 'Hectáreas', 'valores' => [0, 0], 'color' => 'success']]);

    expect($html)->toContain('Nada que mostrar')
        ->and($html)->not->toContain('data-ag-grafico=')
        ->and($html)->not->toContain('<canvas');
});
