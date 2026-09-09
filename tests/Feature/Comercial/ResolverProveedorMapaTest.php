<?php

use App\Dominios\Comercial\Aplicacion\ResolverProveedorMapa;
use App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Tarea 79 (HU-56): proveedor de mapa configurable — primer caso de uso que
 * lee `mapas.*` de LecturaConfiguracion (tarea 78). Reglas cubiertas:
 * sin llave => Leaflet (el camino de siempre); con llave => Google; con
 * llave PERO `proveedor_preferido = 'leaflet'` => Leaflet igual (apagador
 * manual, para no facturar en Google Maps Platform sin querer).
 */

uses(RefreshDatabase::class);

it('sin ninguna configuracion, resuelve a leaflet sin llave', function () {
    $resultado = app(ResolverProveedorMapa::class)->ejecutar();

    expect($resultado)->toBe(['proveedor' => 'leaflet', 'googleMapsApiKey' => null]);
});

it('con llave de google configurada, resuelve a google con la llave', function () {
    Configuracion::query()->create([
        'clave' => 'mapas.google_maps_api_key',
        'valor' => 'AIzaSyD-prueba-0000',
        'grupo' => 'mapas',
        'es_secreto' => true,
    ]);

    $resultado = app(ResolverProveedorMapa::class)->ejecutar();

    expect($resultado)->toBe(['proveedor' => 'google', 'googleMapsApiKey' => 'AIzaSyD-prueba-0000']);
});

it('con llave vacia, resuelve a leaflet aunque la fila exista', function () {
    Configuracion::query()->create([
        'clave' => 'mapas.google_maps_api_key',
        'valor' => '',
        'grupo' => 'mapas',
        'es_secreto' => true,
    ]);

    $resultado = app(ResolverProveedorMapa::class)->ejecutar();

    expect($resultado)->toBe(['proveedor' => 'leaflet', 'googleMapsApiKey' => null]);
});

it('proveedor_preferido=leaflet fuerza leaflet aunque haya llave cargada', function () {
    Configuracion::query()->create([
        'clave' => 'mapas.google_maps_api_key',
        'valor' => 'AIzaSyD-prueba-0000',
        'grupo' => 'mapas',
        'es_secreto' => true,
    ]);
    Configuracion::query()->create([
        'clave' => 'mapas.proveedor_preferido',
        'valor' => 'leaflet',
        'grupo' => 'mapas',
        'es_secreto' => false,
    ]);

    $resultado = app(ResolverProveedorMapa::class)->ejecutar();

    expect($resultado)->toBe(['proveedor' => 'leaflet', 'googleMapsApiKey' => null]);
});
