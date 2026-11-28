<?php

use Tests\TestCase;

/*
 * `molecules/stat-card` es una tarjeta de CIFRA: su valor es un número (conteo, monto, hectáreas,
 * calificación, fracción como `52 / 52`). Nunca un texto (estado, rol, «Sí/No»), una fecha ni un
 * nombre: eso va en la tarjeta de datos de la ficha o en el badge de la cabecera.
 */

uses(TestCase::class);

test('ninguna tarjeta de cifra lleva texto, fechas ni listas en su valor', function () {
    $raiz = dirname(__DIR__, 2);
    $vistas = array_merge(
        glob($raiz.'/app/Dominios/*/Infraestructura/Http/Views/pages/*/*.blade.php') ?: [],
        glob($raiz.'/resources/views/**/*.blade.php') ?: [],
    );

    $violaciones = [];

    foreach ($vistas as $vista) {
        $contenido = (string) file_get_contents($vista);

        if (preg_match_all('~<x-molecules\.stat-card\b(.*?)/>~s', $contenido, $tarjetas) === 0) {
            continue;
        }

        foreach ($tarjetas[1] as $atributos) {
            if (preg_match('~(?<![\w-]):?value="([^"]*)"~s', $atributos, $valor) !== 1) {
                continue;
            }

            if (preg_match('~__\(|->format\(|implode\(|->value\b|->nombre\b|->licencia_numero\b~', $valor[1]) === 1) {
                $violaciones[] = str_replace($raiz.'/', '', $vista).' → value="'.trim($valor[1]).'"';
            }
        }
    }

    expect($violaciones)->toBe([]);
});
