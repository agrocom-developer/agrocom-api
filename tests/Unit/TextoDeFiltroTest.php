<?php

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use Illuminate\Http\Request;

test('filtro de texto: un arreglo en la query no revienta, devuelve vacío', function () {
    $request = Request::create('/panel/clientes', 'GET', ['q' => ['x']]);

    expect(TextoDeFiltro::de($request, 'q'))->toBe('');
});

test('filtro de texto: un texto normal se conserva', function () {
    $request = Request::create('/panel/clientes', 'GET', ['q' => 'Agrocom']);

    expect(TextoDeFiltro::de($request, 'q'))->toBe('Agrocom');
});

test('filtro de texto: ausente devuelve vacío', function () {
    $request = Request::create('/panel/clientes', 'GET');

    expect(TextoDeFiltro::de($request, 'q'))->toBe('');
});

test('filtro de texto: se recorta al largo máximo', function () {
    $request = Request::create('/panel/clientes', 'GET', ['q' => str_repeat('a', 250)]);

    expect(TextoDeFiltro::de($request, 'q', largoMaximo: 200))->toHaveLength(200);
});
