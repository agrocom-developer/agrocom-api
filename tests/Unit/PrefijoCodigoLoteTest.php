<?php

use App\Dominios\Comercial\Dominio\PrefijoCodigoLote;

/*
 * La edición en bloque propone el prefijo a partir de los códigos que la
 * propiedad ya tiene, para que los lotes nuevos sigan la misma numeración.
 */
test('lotes: el prefijo propuesto es el más usado entre los códigos que terminan en número', function () {
    expect(PrefijoCodigoLote::inferir(['Lote 1', 'Lote 2', 'Parcela 1']))->toBe('Lote ')
        ->and(PrefijoCodigoLote::inferir(['Parcela 1', 'Parcela 2', 'Lote 1']))->toBe('Parcela ')
        ->and(PrefijoCodigoLote::inferir(['A-10', 'A-11']))->toBe('A-');
});

test('lotes: sin códigos numerados se propone el prefijo por defecto', function () {
    expect(PrefijoCodigoLote::inferir([]))->toBe(PrefijoCodigoLote::POR_DEFECTO)
        ->and(PrefijoCodigoLote::inferir(['Norte', 'Sur']))->toBe(PrefijoCodigoLote::POR_DEFECTO)
        ->and(PrefijoCodigoLote::inferir(['12', '13']))->toBe(PrefijoCodigoLote::POR_DEFECTO);
});

test('lotes: un código con el número pegado al texto conserva todo lo anterior como prefijo', function () {
    expect(PrefijoCodigoLote::inferir(['Lote 10', 'Lote 9']))->toBe('Lote ')
        ->and(PrefijoCodigoLote::inferir(['B2C7']))->toBe('B2C');
});
