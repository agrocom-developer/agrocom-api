<?php

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

/*
 * Bug real reportado por el usuario (11/9/2026), mirando el panel en el
 * celular: en `organisms/mobile-topbar` la campana y el avatar eran
 * `<span>` decorativos (sin `data-bs-toggle`) y la lupa un ícono suelto —
 * no había forma de ver notificaciones, entrar a "Mi perfil", cambiar de
 * tema NI DE CERRAR SESIÓN desde el celular. No es solo un detalle visual:
 * por eso este test no se conforma con `assertOk`, verifica que los
 * CONTROLES REALES lleguen al HTML — la molecule compartida con el topbar
 * de escritorio (`molecules/notifications-menu`, `molecules/user-menu`) y
 * el fix de `resources/js/organisms/topbar.js` (antes `querySelector`
 * único para el logout, ahora `querySelectorAll`: con topbar y
 * mobile-topbar los dos en el DOM a la vez, cada uno con su propio botón,
 * el segundo quedaba sin wireear).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);
});

function panelAsignarRolMobile(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

it('el mobile-topbar trae controles reales de tema, notificaciones, usuario y búsqueda', function () {
    $usuario = SecUser::factory()->create();
    $idRol = panelAsignarRolMobile($usuario, 'dueno');

    $respuesta = $this->actingAs($usuario, 'interno')
        ->withSession(['sec_rol_activo_id' => $idRol])
        ->get(route('panel.dashboard'));

    $respuesta->assertOk();

    // Tema: el toggle real vive en el mobile-topbar, no solo en escritorio.
    $respuesta->assertSee('ag-mobile-topbar__theme-toggle', false);

    // Notificaciones y usuario: botones reales con dropdown de Bootstrap,
    // no `<span>` decorativos — la molecule compartida los vuelve
    // idénticos al topbar de escritorio salvo la clase del disparador.
    $respuesta->assertSee('ag-mobile-topbar__bell-btn', false);
    $respuesta->assertSee('ag-mobile-topbar__avatar-btn', false);
    $respuesta->assertSeeInOrder(['data-bs-toggle="dropdown"', 'data-bs-toggle="dropdown"'], false);

    // Logout alcanzable desde el menú de usuario de mobile.
    $respuesta->assertSee('data-ag-logout', false);

    // Búsqueda real (checkbox + input, sin JS) — no un ícono suelto.
    $respuesta->assertSee('ag-mobile-topbar__search-toggle', false);
    $respuesta->assertSee('type="search"', false);
});

it('molecules/user-menu compacto (mobile) no incluye "Cambiar de rol" sin cambiarRolHref', function () {
    $html = Blade::render(
        '<x-molecules.user-menu user-name="Carlos Ferrufino" compact trigger-class="ag-mobile-topbar__avatar-btn" />'
    );

    // El disparador y el logout sí tienen que estar — lo que NO debe
    // aparecer es "Cambiar de rol": mobile-topbar no pasa `cambiarRolHref`
    // a propósito, porque `organisms/module-drawer` ya tiene su propio
    // link al pie y duplicarlo acá sería un segundo camino a lo mismo.
    expect($html)
        ->toContain('ag-mobile-topbar__avatar-btn')
        ->toContain('data-ag-logout')
        ->not->toContain(__('seguridad.rol.switch_trigger'));
});

it('molecules/user-menu completo (escritorio) SÍ incluye "Cambiar de rol" cuando se pasa cambiarRolHref', function () {
    $html = Blade::render(
        '<x-molecules.user-menu user-name="Carlos Ferrufino" active-role-label="Dueño" cambiar-rol-href="/panel/seleccionar-rol" />'
    );

    expect($html)->toContain(__('seguridad.rol.switch_trigger'));
});
