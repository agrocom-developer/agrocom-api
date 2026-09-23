<?php

use App\Dominios\Finanzas\Dominio\EstadoRendicion;
use App\Dominios\Finanzas\Dominio\PoliticaEdicionGasto;

/*
 * Regla de edición de un gasto (tarea 134): se corrige mientras su rendición
 * asociada, si tiene una, siga `Abierta`. Pura, sin app ni DB.
 */

test('admiteEdicion: sin rendición asociada, siempre editable', function () {
    expect(PoliticaEdicionGasto::admiteEdicion(null))->toBeTrue();
});

test('admiteEdicion: rendición Abierta, editable', function () {
    expect(PoliticaEdicionGasto::admiteEdicion(EstadoRendicion::Abierta))->toBeTrue();
});

test('admiteEdicion: rendición Presentada o Aprobada, no editable', function () {
    expect(PoliticaEdicionGasto::admiteEdicion(EstadoRendicion::Presentada))->toBeFalse();
    expect(PoliticaEdicionGasto::admiteEdicion(EstadoRendicion::Aprobada))->toBeFalse();
});
