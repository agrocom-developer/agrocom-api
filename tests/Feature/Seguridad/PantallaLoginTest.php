<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/*
 * GET /login y GET /portal/login — el HTML que se sirve antes de que corra
 * una línea de JS.
 *
 * Nace de un bug de campo (9/9/2026): recargar `/login` mostraba el tab
 * "recuperar acceso" en vez del de ingreso. La página pasa
 * `$errors->first('email')` al organism, y eso devuelve CADENA VACÍA cuando
 * no hay error — nunca `null`, que era contra lo que el organism comparaba.
 * Con la comparación estricta, "no hay error" y "hay error" eran el mismo
 * caso y toda visita limpia caía en el panel equivocado.
 *
 * Lo que se prueba acá es el estado inicial servido, no el comportamiento de
 * los tabs con JS (eso es `organisms/login-form.js`, cubierto por la suite
 * visual): qué panel llega visible en la primera pintura.
 */

uses(RefreshDatabase::class);

it('sirve el tab de ingreso en una visita limpia a /login', function () {
    $this->get(route('login.form'))
        ->assertOk()
        ->assertDontSee('data-ag-login-tab-inicial', false)
        ->assertSee('id="tab-ingreso"', false)
        ->assertSee(__('seguridad.login.titulo'), false);
});

it('sirve el tab de ingreso en una visita limpia a /portal/login', function () {
    $this->get(route('portal.login.form'))
        ->assertOk()
        ->assertDontSee('data-ag-login-tab-inicial', false);
});

it('vuelve al tab de recuperar acceso después de pedir el enlace', function () {
    Notification::fake();

    $this->post(route('recuperar.store'), ['email' => 'alguien@agrocom.example'])
        ->assertRedirect(route('login.form'));

    $this->get(route('login.form'))
        ->assertOk()
        ->assertSee('data-ag-login-tab-inicial="recuperar"', false)
        ->assertSee(__('seguridad.recuperar.estado_generico'), false);
});

it('vuelve al tab de recuperar acceso cuando el email no tiene forma de email', function () {
    $this->from(route('login.form'))
        ->post(route('recuperar.store'), ['email' => 'no-es-un-email'])
        ->assertRedirect(route('login.form'));

    $this->get(route('login.form'))
        ->assertOk()
        ->assertSee('data-ag-login-tab-inicial="recuperar"', false);
});

/*
 * El script inline anti-parpadeo (`atoms/tema-inicial`). Va en el `<head>` de
 * toda página que sirva UI, y tiene que estar ANTES del `@vite` del CSS: es
 * lo único que puede fijar `data-bs-theme` antes del primer pintado. Lo que
 * este test cuida es que nadie lo saque de una página al editar su `<head>`
 * — sin él vuelve el salto de claro a oscuro que motivó el cambio.
 */
it('todas las páginas públicas traen el script que fija el tema antes de pintar', function (string $ruta) {
    $this->get($ruta)
        ->assertOk()
        ->assertSee("localStorage.getItem('ag-theme')", false)
        ->assertSee('data-ag-tema-servidor', false);
})->with([
    'login' => fn () => route('login.form'),
    'portal' => fn () => route('portal.login.form'),
]);
