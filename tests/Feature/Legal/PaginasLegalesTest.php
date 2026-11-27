<?php

use Illuminate\Support\Facades\Route;

/*
 * Humo de las páginas legales públicas (docs/politicas/legal.md): términos y
 * privacidad existen, responden sin sesión, muestran la versión y la vigencia
 * de `config/legal.php`, no llevan aviso de borrador ni marcas pendientes, y
 * omiten sin marcas los datos de contacto que no están configurados. No toca
 * la base de datos.
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

it('no muestra aviso de borrador ni marcas de pendiente', function (string $documento, string $ruta) {
    $this->get($ruta)
        ->assertOk()
        ->assertDontSee('BORRADOR')
        ->assertDontSee('POR DEFINIR')
        ->assertDontSee('data-ag-legal-borrador', false);

    expect(file_get_contents(base_path('lang/es/legal.php')))
        ->not->toContain('[POR DEFINIR]')
        ->not->toContain('borrador_titulo');
})->with('documentos legales');

it('trae la versión 2026-2 vigente desde el 27 de noviembre de 2026', function (string $documento) {
    expect(config("legal.documentos.{$documento}.version"))->toBe('2026-2')
        ->and(config("legal.documentos.{$documento}.vigente_desde"))->toBe('2026-11-27');
})->with(['terminos', 'privacidad']);

it('nombra el producto y el titular y no deja marcadores sin resolver', function (string $documento, string $ruta) {
    $this->get($ruta)
        ->assertOk()
        ->assertSee('DronAgro')
        ->assertSee('Ciudad Comercio, pasillo J, oficina 229')
        ->assertDontSee(':producto')
        ->assertDontSee(':titular')
        ->assertDontSee(':meses')
        ->assertDontSee(':dias_sin_antecedentes')
        ->assertDontSee(':gracia')
        ->assertDontSee(':plazo')
        ->assertDontSee(':respaldos')
        ->assertDontSee(':servicio_meses')
        ->assertDontSee(':emergencia_dias')
        ->assertDontSee(':valor');
})->with('documentos legales');

it('omite sin marcas los datos de contacto que no están configurados', function (string $documento, string $ruta) {
    config([
        'legal.contacto' => ['correo' => null, 'telefono' => null, 'whatsapp' => null],
        'legal.redes' => ['facebook' => null, 'instagram' => null, 'tiktok' => null, 'youtube' => null],
        'legal.empresa' => ['razon_social' => null, 'nit' => null, 'representante' => null, 'jurisdiccion' => null],
    ]);

    $this->get($ruta)
        ->assertOk()
        ->assertSee('data-ag-legal-contacto', false)
        ->assertDontSee('Correo electrónico')
        ->assertDontSee('NIT')
        ->assertDontSee('Redes sociales oficiales')
        ->assertDontSee('tribunales de');
})->with('documentos legales');

it('muestra los datos de contacto cuando están configurados', function (string $documento, string $ruta) {
    config([
        'legal.contacto' => ['correo' => 'soporte@ejemplo.test', 'telefono' => '+591 70000000', 'whatsapp' => null],
        'legal.redes' => ['facebook' => 'dronagro.bo', 'instagram' => null, 'tiktok' => null, 'youtube' => null],
        'legal.empresa' => ['razon_social' => 'Agrocom Ejemplo S.R.L.', 'nit' => '123456', 'representante' => null, 'jurisdiccion' => 'Santa Cruz de la Sierra'],
    ]);

    $this->get($ruta)
        ->assertOk()
        ->assertSee('soporte@ejemplo.test')
        ->assertSee('+591 70000000')
        ->assertSee('Agrocom Ejemplo S.R.L.')
        ->assertSee('123456')
        ->assertSee('Facebook (dronagro.bo)')
        ->assertDontSee('WhatsApp:');
})->with('documentos legales');

it('los términos dicen que el uso es sin costo por ahora y que los precios se avisarán', function () {
    $this->get('/terminos')
        ->assertOk()
        ->assertSee('Por ahora, usar DronAgro no tiene costo')
        ->assertSee('con anticipación');
});

it('la política da los plazos de conservación y eliminación del código', function () {
    $this->get('/privacidad')
        ->assertOk()
        ->assertSee('Tienes 7 días para arrepentirte')
        ->assertSee('90 días si no tuviste antecedentes y 12 meses si los tuviste')
        ->assertSee('hash irreversible');
});

it('cada documento enlaza al otro y el pie del login enlaza a los dos', function () {
    $this->get('/terminos')->assertSee(route('legal.privacidad'), false);
    $this->get('/privacidad')->assertSee(route('legal.terminos'), false);

    $this->get('/login')
        ->assertOk()
        ->assertSee(route('legal.terminos'), false)
        ->assertSee(route('legal.privacidad'), false);
});
