<?php

use App\Dominios\Comercial\Dominio\ColorPropiedad;

/*
 * Una propiedad siempre tiene color (pedido directo del 19/9/2026): el verde
 * por defecto tiene que ser un valor real de la paleta, no un hex suelto.
 */
test('propiedad: el color por defecto es el verde y pertenece a la paleta', function () {
    expect(ColorPropiedad::porDefecto())->toBe(ColorPropiedad::VerdeBosque)
        ->and(ColorPropiedad::valores())->toContain(ColorPropiedad::porDefecto()->value);
});
