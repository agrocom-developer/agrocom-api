<?php

use App\Dominios\Finanzas\Dominio\EstadoRendicion;
use App\Dominios\Finanzas\Dominio\PoliticaEdicionRendicion;

/*
 * Regla de edición de la cabecera de una rendición (tarea 134): se corrige
 * solo mientras sigue `Abierta` — en cuanto pasa a `Presentada` ya congeló
 * su monto, y `Aprobada` ya es historia liquidada. Pura, sin app ni DB.
 */

test('admiteEdicion: Abierta se edita', function () {
    expect(PoliticaEdicionRendicion::admiteEdicion(EstadoRendicion::Abierta))->toBeTrue();
});

test('admiteEdicion: Presentada y Aprobada no se editan', function () {
    expect(PoliticaEdicionRendicion::admiteEdicion(EstadoRendicion::Presentada))->toBeFalse();
    expect(PoliticaEdicionRendicion::admiteEdicion(EstadoRendicion::Aprobada))->toBeFalse();
});
