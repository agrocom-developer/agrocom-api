<?php

/*
 * Filtro «Aplicación» del listado de órdenes: las opciones se escriben en
 * palabras («Primera aplicación», «Segunda aplicación»…) y las pide el
 * controlador por clave armada (`aplicacion_ordinal.{n}`), que la compuerta de
 * `ClavesDeIdiomaTest` no puede leer. Este test cubre que estén todas.
 */

test('el filtro de aplicación tiene su ordinal escrito del 1 al 10', function () {
    /** @var array{ordenes: array{aplicacion_ordinal: array<int, string>, aplicacion_numero: string}} $lang */
    $lang = require dirname(__DIR__, 2).'/lang/es/operaciones.php';

    expect(array_keys($lang['ordenes']['aplicacion_ordinal']))->toBe(range(1, 10))
        ->and($lang['ordenes']['aplicacion_ordinal'][1])->toBe('Primera aplicación')
        ->and($lang['ordenes']['aplicacion_ordinal'][2])->toBe('Segunda aplicación')
        // Más allá de la décima el controlador cae a «Aplicación N».
        ->and($lang['ordenes']['aplicacion_numero'])->toContain(':nro');

    foreach ($lang['ordenes']['aplicacion_ordinal'] as $texto) {
        expect($texto)->toEndWith(' aplicación');
    }
});
