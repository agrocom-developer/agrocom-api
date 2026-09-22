<?php

use App\Dominios\Comercial\Dominio\OrdenCodigoLote;

/*
 * La tabla de lotes del contrato ordena los códigos como los lee una persona:
 * por número, no letra por letra.
 */
function ordenarCodigos(array $codigos): array
{
    usort($codigos, OrdenCodigoLote::comparar(...));

    return $codigos;
}

test('lotes: los códigos se ordenan por su número, no letra por letra', function () {
    expect(ordenarCodigos(['L10', 'L2', 'L1', 'L21', 'L11', 'L20', 'L3']))
        ->toBe(['L1', 'L2', 'L3', 'L10', 'L11', 'L20', 'L21'])
        ->and(ordenarCodigos(['Lote10', 'Lote9', 'Lote100', 'Lote1']))
        ->toBe(['Lote1', 'Lote9', 'Lote10', 'Lote100'])
        ->and(ordenarCodigos(['12', '2', '1']))
        ->toBe(['1', '2', '12']);
});

test('lotes: lo que va delante del número conserva su orden literal, sin distinguir mayúsculas', function () {
    expect(ordenarCodigos(['Sur 2', 'Norte 10', 'sur 1', 'Norte 2', 'Norte 1']))
        ->toBe(['Norte 1', 'Norte 2', 'Norte 10', 'sur 1', 'Sur 2'])
        ->and(ordenarCodigos(['B-1', 'A-10', 'A-2']))
        ->toBe(['A-2', 'A-10', 'B-1']);
});

test('lotes: un código sin número queda antes que los numerados de su mismo prefijo', function () {
    expect(ordenarCodigos(['Lote 2', 'Lote', 'Lote 1']))->toBe(['Lote', 'Lote 1', 'Lote 2']);
});
