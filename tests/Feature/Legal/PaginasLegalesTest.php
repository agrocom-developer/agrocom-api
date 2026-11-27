<?php

use Illuminate\Support\Facades\Route;

/*
 * Humo de las páginas legales públicas (docs/politicas/legal.md): términos y
 * privacidad existen, responden sin sesión y muestran la versión, la vigencia
 * y el aviso de borrador que salen de `config/legal.php`. No toca la base de
 * datos ni valida el texto legal.
 */

beforeEach(function () {
    $this->withoutVite();

    // El idioma del panel sale de `APP_LOCALE=es` del `.env`; la prueba no
    // depende de ese archivo (CI no lo tiene y caería en el idioma por
    // defecto del framework, sin textos).
    app()->setLocale('es');
    app()->setFallbackLocale('es');
});

dataset('documentos legales', [
    'términos' => ['terminos', '/terminos', 'legal.terminos'],
    'privacidad' => ['privacidad', '/privacidad', 'legal.privacidad'],
]);

it('responde sin sesión y muestra título, versión y vigencia', function (string $documento, string $ruta) {
    $respuesta = $this->get($ruta);

    $respuesta->assertOk();
    $respuesta->assertSee(__("legal.{$documento}.titulo"));
    $respuesta->assertSee(__('legal.comun.version', ['version' => config("legal.documentos.{$documento}.version")]));
    $respuesta->assertSee('data-ag-legal-version', false);
})->with('documentos legales');

it('tiene nombre de ruta y no pide autenticación', function (string $documento, string $ruta, string $nombre) {
    $laRuta = Route::getRoutes()->getByName($nombre);

    expect($laRuta)->not->toBeNull()
        ->and($laRuta->uri())->toBe(ltrim($ruta, '/'));

    $autenticacion = collect($laRuta->gatherMiddleware())
        ->filter(fn ($middleware) => is_string($middleware) && (str_starts_with($middleware, 'auth') || str_contains($middleware, 'Authenticate')));

    expect($autenticacion)->toBeEmpty();
})->with('documentos legales');

it('muestra el aviso de borrador mientras el parámetro está activado', function (string $documento, string $ruta) {
    config(['legal.borrador' => true]);

    $this->get($ruta)
        ->assertOk()
        ->assertSee(__('legal.comun.borrador_titulo'))
        ->assertSee('data-ag-legal-borrador', false);
})->with('documentos legales');

it('oculta el aviso de borrador cuando el parámetro se apaga', function (string $documento, string $ruta) {
    config(['legal.borrador' => false]);

    $this->get($ruta)
        ->assertOk()
        ->assertDontSee(__('legal.comun.borrador_titulo'))
        ->assertDontSee('data-ag-legal-borrador', false);
})->with('documentos legales');

it('trae el aviso de borrador activado por defecto', function () {
    $porDefecto = require base_path('config/legal.php');

    expect($porDefecto['borrador'])->toBeTrue();
});

it('resalta lo que falta definir y no deja marcadores sin resolver', function (string $documento, string $ruta) {
    $this->get($ruta)
        ->assertOk()
        ->assertSee('ag-legal__por-definir', false)
        ->assertSee('[POR DEFINIR]')
        ->assertDontSee(':meses');
})->with('documentos legales');

it('cada documento enlaza al otro y el pie del login enlaza a los dos', function () {
    $this->get('/terminos')->assertSee(route('legal.privacidad'), false);
    $this->get('/privacidad')->assertSee(route('legal.terminos'), false);

    $this->get('/login')
        ->assertOk()
        ->assertSee(route('legal.terminos'), false)
        ->assertSee(route('legal.privacidad'), false);
});
