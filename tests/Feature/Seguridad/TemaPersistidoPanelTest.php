<?php

use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-02, CA "preferencia de tema persistida por usuario" — el tramo que
 * faltaba entre la fila y la pantalla.
 *
 * `SecUserPreferenciaTest` prueba que la fila se guarda y `PreferenciaRolTest`
 * que el POST del toggle la actualiza; acá se cierra el circuito: que el
 * `data-bs-theme` del HTML servido salga de esa fila, que sea POR USUARIO (no
 * por sesión ni global) y que sobreviva a una sesión nueva — si viviera solo
 * en `localStorage`, todos estos pasarían igual y la preferencia no estaría
 * persistida en ningún lado del servidor.
 *
 * Usa `jefe_campo` como rol de prueba (no `piloto`): desde la tarea 62 (fuga
 * 2) dashboard y organización exigen `seguridad.dashboard.ver`/
 * `.organizacion.ver`, que `piloto` no tiene — su única pantalla propia
 * (`panel.devengos.*`) exige además una `persona_id`, ruido innecesario para
 * lo que este archivo prueba. `jefe_campo` sí tiene ambos permisos y sigue
 * sin `seguridad.usuario.ver`, así que conserva la asserción "no ve usuarios".
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);
});

function temaAsignarRol(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

it('sin preferencia guardada el panel se sirve en claro', function () {
    $usuario = SecUser::factory()->create();
    $idJefeCampo = temaAsignarRol($usuario, 'jefe_campo');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idJefeCampo]);

    $this->get(route('panel.dashboard'))
        ->assertOk()
        ->assertSee('data-bs-theme="light"', false);
});

it('el panel se sirve con el tema persistido del usuario', function () {
    $usuario = SecUser::factory()->create();
    $idJefeCampo = temaAsignarRol($usuario, 'jefe_campo');

    SecUserPreferencia::query()->create([
        'user_id' => $usuario->id,
        'tema' => TemaPreferencia::Oscuro,
    ]);

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idJefeCampo]);

    $this->get(route('panel.dashboard'))
        ->assertOk()
        ->assertSee('data-bs-theme="dark"', false)
        ->assertDontSee('data-bs-theme="light"', false);
});

it('el tema elegido con el toggle se sirve así en la próxima página, sin depender del navegador', function () {
    $usuario = SecUser::factory()->create();
    $idJefeCampo = temaAsignarRol($usuario, 'jefe_campo');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idJefeCampo]);

    $this->postJson(route('panel.preferencias.tema'), ['tema' => 'dark'])->assertOk();

    $this->get(route('panel.usuarios.index'))->assertStatus(403); // jefe_campo no ve usuarios
    $this->get(route('panel.organizacion.index'))
        ->assertOk()
        ->assertSee('data-bs-theme="dark"', false);
});

it('la preferencia sobrevive a una sesión nueva: vive en el usuario, no en la sesión', function () {
    $usuario = SecUser::factory()->create();
    $idJefeCampo = temaAsignarRol($usuario, 'jefe_campo');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idJefeCampo]);
    $this->postJson(route('panel.preferencias.tema'), ['tema' => 'dark'])->assertOk();

    $this->flushSession();
    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idJefeCampo]);

    $this->get(route('panel.dashboard'))
        ->assertOk()
        ->assertSee('data-bs-theme="dark"', false);
});

it('la preferencia es por usuario: otro usuario sigue en claro', function () {
    $oscuro = SecUser::factory()->create();
    $idJefeCampo = temaAsignarRol($oscuro, 'jefe_campo');
    SecUserPreferencia::query()->create([
        'user_id' => $oscuro->id,
        'tema' => TemaPreferencia::Oscuro,
    ]);

    $claro = SecUser::factory()->create();
    $idJefeCampoOtro = temaAsignarRol($claro, 'jefe_campo');

    $this->actingAs($oscuro, 'interno')->withSession(['sec_rol_activo_id' => $idJefeCampo]);
    $this->get(route('panel.dashboard'))->assertSee('data-bs-theme="dark"', false);

    $this->flushSession();
    $this->actingAs($claro, 'interno')->withSession(['sec_rol_activo_id' => $idJefeCampoOtro]);
    $this->get(route('panel.dashboard'))->assertSee('data-bs-theme="light"', false);
});

/*
 * 9/9/2026 — el parpadeo. El tema persistido llegaba bien en el `<html>`,
 * pero cuando el navegador tenía guardado otro (caso normal apenas alguien
 * usa el toggle en la pantalla de login, donde no hay sesión que persista
 * nada) `theme-toggle.js` lo corregía recién en `DOMContentLoaded`: la
 * página se pintaba entera en claro y saltaba a oscuro. Medido sobre la
 * grabación del usuario, ~130 ms de blanco en cada clic del menú.
 *
 * El arreglo es un script inline en el `<head>`, antes del CSS. Este test
 * cuida que siga estando en el shell del panel — es la parte servida del
 * arreglo, y la única que la suite PHP puede ver.
 */
it('el shell del panel trae el script que fija el tema antes del primer pintado', function () {
    $usuario = SecUser::factory()->create();
    $idJefeCampo = temaAsignarRol($usuario, 'jefe_campo');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idJefeCampo]);

    $respuesta = $this->get(route('panel.dashboard'))->assertOk();

    $html = $respuesta->getContent();
    $posicionScript = strpos($html, "localStorage.getItem('ag-theme')");

    expect($posicionScript)->not->toBeFalse()
        ->and(str_contains($html, 'data-ag-tema-servidor'))->toBeTrue();

    // Dentro del `<head>`: un script en el `<body>` ya llega tarde, el
    // navegador pintó con el tema del servidor antes de leerlo. (No se
    // compara contra el `<link>` del CSS: en la suite `@vite` no resuelve un
    // manifest real y el nombre del bundle no es estable.)
    $posicionCierreHead = strpos($html, '</head>');
    expect($posicionCierreHead)->not->toBeFalse()
        ->and($posicionScript)->toBeLessThan($posicionCierreHead);
});
