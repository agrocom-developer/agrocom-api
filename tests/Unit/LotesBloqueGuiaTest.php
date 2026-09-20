<?php

use App\Dominios\Comercial\Infraestructura\Http\Requests\GenerarLotesRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\LotesBloqueRequest;

/*
 * Lotes en bloque (crear y editar): las hectáreas por lote son una GUÍA, nunca
 * una regla (19/9/2026, pedido directo). Una propiedad no es toda lote —tiene
 * hacienda, agua, caminos—, un lote irregular no impide crear los demás, y
 * muchas propiedades no tienen la superficie cargada. Ningún guardado compara
 * la suma de los lotes con las hectáreas de la propiedad, ni con 0.
 *
 * Puro, sin app ni DB: se lee qué reglas declara la request y qué toca cada
 * archivo del flujo. Lo que sí se limita es cuántos lotes se crean por vez,
 * una red contra un dedo de más (`LOTES_MAXIMOS_POR_TANDA`), no una regla de
 * negocio.
 */

/** @return list<string> */
function archivosDeLotesEnBloque(): array
{
    $raiz = dirname(__DIR__, 2).'/app/Dominios/Comercial';

    return [
        $raiz.'/Infraestructura/Http/Requests/LotesBloqueRequest.php',
        $raiz.'/Infraestructura/Http/Requests/GenerarLotesRequest.php',
        $raiz.'/Infraestructura/Http/Requests/EditarLotesBloqueRequest.php',
        $raiz.'/Aplicacion/CrearLotesMasivo.php',
        $raiz.'/Aplicacion/EditarLotesEnBloque.php',
    ];
}

test('lotes en bloque: las hectáreas por lote solo se validan como un número positivo', function () {
    $reglas = (new GenerarLotesRequest)->rules();

    // Solo forma (obligatoria, numérica, mayor a cero, dentro del DECIMAL de la
    // tabla): ninguna regla mira la propiedad ni otro campo.
    expect($reglas['hectareas'])->toBe(['required', 'numeric', 'gt:0', 'max:99999999.99']);
});

test('lotes en bloque: ningún archivo del flujo compara con la superficie de la propiedad', function () {
    foreach (archivosDeLotesEnBloque() as $archivo) {
        $codigo = file_get_contents($archivo);

        // Se ignoran los comentarios: los docblocks explican justo esto.
        $sinComentarios = preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', (string) $codigo) ?? '';

        expect($sinComentarios)
            ->not->toMatch('/\$propiedad\s*->\s*hectareas/')
            ->not->toMatch('/\bsuperficie\b/i');
    }
});

test('lotes en bloque: la cantidad tiene un tope por tanda, no por propiedad', function () {
    $reglas = (new GenerarLotesRequest)->rules();

    expect($reglas['cantidad'])->toBe(['required', 'integer', 'min:1', 'max:'.LotesBloqueRequest::LOTES_MAXIMOS_POR_TANDA])
        // Alcanza para los casos reales (34, 100 lotes) sin pedir tandas.
        ->and(LotesBloqueRequest::LOTES_MAXIMOS_POR_TANDA)->toBeGreaterThanOrEqual(100);
});
