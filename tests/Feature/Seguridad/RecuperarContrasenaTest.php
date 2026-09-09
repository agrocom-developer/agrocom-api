<?php

use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use App\Dominios\Seguridad\Infraestructura\Notificaciones\RestablecerContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/*
 * Tarea 66 — "Recuperar acceso" (ADR 0004, ampliación 9/9/2026): pide el
 * enlace de restablecimiento. La respuesta es SIEMPRE la misma (mismo
 * redirect, mismo mensaje) exista o no la cuenta, esté bloqueada o no —
 * nunca revela qué correos existen en el sistema.
 *
 * La verificación de "sin token CSRF → 419" quedó FUERA de esta suite a
 * propósito: `PreventRequestForgery::runningUnitTests()` deshabilita la
 * verificación de CSRF para toda la suite de tests (igual que en el resto
 * del panel — ningún test existente manda `_token`), así que no hay forma
 * de ejercitar ese 419 desde Pest sin apagar un mecanismo del framework
 * ajeno a esta tarea. Se verificó a mano contra el servidor real
 * (`http://localhost:8000`): un POST a `/recuperar` sin `_token` responde
 * 419 — documentado en runs/66.md.
 */

uses(RefreshDatabase::class);

it('envía la notificación a un email existente y activo, con el mismo mensaje que a uno inexistente', function () {
    Notification::fake();

    $usuario = SecUser::factory()->create(['email' => 'existe@agrocom.example']);

    $respuestaExistente = $this->post(route('recuperar.store'), ['email' => 'existe@agrocom.example']);
    $respuestaInexistente = $this->post(route('recuperar.store'), ['email' => 'no.existe@agrocom.example']);

    $respuestaExistente->assertRedirect(route('login.form'))
        ->assertSessionHas('estado', __('seguridad.recuperar.estado_generico'));

    $respuestaInexistente->assertRedirect(route('login.form'))
        ->assertSessionHas('estado', __('seguridad.recuperar.estado_generico'));

    // `notify()` corre sobre la instancia que resuelve el provider del broker
    // (`SecUsuarioInterno`, subtipo de `SecUser`) — `NotificationFake` indexa
    // por clase exacta (`get_class`), así que la aserción tiene que apuntar
    // a ese mismo subtipo, no al `SecUser` crudo de la fixture.
    Notification::assertSentToTimes(SecUsuarioInterno::query()->findOrFail($usuario->id), RestablecerContrasena::class, 1);
});

it('no envía nada a un email que no pertenece a ninguna cuenta', function () {
    Notification::fake();

    $this->post(route('recuperar.store'), ['email' => 'fantasma@agrocom.example'])
        ->assertRedirect(route('login.form'));

    Notification::assertNothingSent();
});

it('no envía nada a una cuenta bloqueada (state = false)', function () {
    Notification::fake();

    $usuario = SecUser::factory()->create(['email' => 'bloqueado@agrocom.example', 'state' => false]);

    $this->post(route('recuperar.store'), ['email' => 'bloqueado@agrocom.example'])
        ->assertRedirect(route('login.form'));

    Notification::assertNotSentTo($usuario, RestablecerContrasena::class);
});

it('no envía nada a una cuenta borrada', function () {
    Notification::fake();

    $usuario = SecUser::factory()->create(['email' => 'borrado@agrocom.example']);
    $usuario->delete();

    $this->post(route('recuperar.store'), ['email' => 'borrado@agrocom.example'])
        ->assertRedirect(route('login.form'));

    Notification::assertNothingSent();
});

it('un pedido de recuperación del portal no resuelve el email de una cuenta interna (broker por guard)', function () {
    Notification::fake();

    $interno = SecUser::factory()->create(['email' => 'interno@agrocom.example', 'type' => TipoUsuario::Interno]);

    $this->post(route('portal.recuperar.store'), ['email' => 'interno@agrocom.example'])
        ->assertRedirect(route('portal.login.form'));

    // El broker `cliente` resuelve por `SecUsuarioCliente` (scope
    // `type = 'cliente'`): un correo de cuenta interna no matchea nada ahí.
    Notification::assertNothingSent();
    expect($interno)->not->toBeNull();
});
