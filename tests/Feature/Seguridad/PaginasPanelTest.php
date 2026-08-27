<?php

use App\Dominios\Seguridad\Aplicacion\ItemMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-02 — páginas GET del panel que `frontend` va a ensamblar: selector de
 * rol (panel.rol-activo.selector), dashboard (panel.dashboard) y el
 * placeholder de "Usuarios" (panel.usuarios.index). Cada test verifica que
 * la ruta resuelve los datos correctos (ADR 0008: los controladores son
 * adaptadores delgados) — no el contenido visual final, que es alcance de
 * `frontend`/`design-ui`.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);
});

function panelAsignarRol(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

// --- GET /panel/seleccionar-rol -------------------------------------------

it('el selector de rol responde 200 con la lista de roles vivos cuando hay 2+', function () {
    $usuario = SecUser::factory()->create();
    panelAsignarRol($usuario, 'piloto');
    panelAsignarRol($usuario, 'auxiliar');

    $this->actingAs($usuario, 'interno');

    $respuesta = $this->get(route('panel.rol-activo.selector'));

    $respuesta->assertOk()
        ->assertViewIs('seguridad::pages.seleccionar-rol')
        ->assertViewHas('roles', fn ($roles) => $roles->count() === 2);

    expect(session('sec_rol_activo_id'))->toBeNull();
});

it('el selector de rol con un único rol vivo lo fija solo y redirige al dashboard', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = panelAsignarRol($usuario, 'piloto');

    $this->actingAs($usuario, 'interno');

    $respuesta = $this->get(route('panel.rol-activo.selector'));

    $respuesta->assertRedirect(route('panel.dashboard'));
    expect(session('sec_rol_activo_id'))->toBe($idPiloto);
});

it('el selector de rol sin ningún rol vivo responde 200 con la lista vacía, sin redirigir', function () {
    $usuario = SecUser::factory()->create();

    $this->actingAs($usuario, 'interno');

    $respuesta = $this->get(route('panel.rol-activo.selector'));

    $respuesta->assertOk()
        ->assertViewIs('seguridad::pages.seleccionar-rol')
        ->assertViewHas('roles', fn ($roles) => $roles->isEmpty());
});

it('el selector de rol exige autenticación', function () {
    $this->getJson(route('panel.rol-activo.selector'))->assertStatus(401);
});

// --- GET /panel/dashboard ---------------------------------------------------

it('el dashboard responde 200 con el árbol de menú del rol activo de la sesión', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = panelAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $respuesta = $this->get(route('panel.dashboard'));

    $respuesta->assertOk()
        ->assertViewIs('seguridad::pages.dashboard')
        ->assertViewHas('rolActivoId', $idDueno)
        ->assertViewHas('activeRoleLabel', 'dueno')
        ->assertViewHas('userName', $usuario->name)
        ->assertViewHas('menu', fn (array $menu): bool => $menu !== [] && $menu[0] instanceof ItemMenu);
});

it('el dashboard sin rol activo resoluble no deja pasar (409 vía middleware rol.activo)', function () {
    $usuario = SecUser::factory()->create();
    panelAsignarRol($usuario, 'piloto');
    panelAsignarRol($usuario, 'auxiliar');

    $this->actingAs($usuario, 'interno');

    $this->getJson(route('panel.dashboard'))->assertStatus(409);
});

it('el dashboard exige autenticación', function () {
    $this->getJson(route('panel.dashboard'))->assertStatus(401);
});

// --- GET /panel/usuarios ----------------------------------------------------

it('el placeholder de usuarios responde 200 para un rol activo con seguridad.usuario.ver', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = panelAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->get(route('panel.usuarios.index'))
        ->assertOk()
        ->assertViewIs('seguridad::pages.usuarios.index');
});

it('el placeholder de usuarios responde 403 para un rol activo sin seguridad.usuario.ver', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = panelAsignarRol($usuario, 'piloto');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idPiloto]);

    $this->get(route('panel.usuarios.index'))->assertStatus(403);
});

it('el placeholder de usuarios exige autenticación', function () {
    $this->getJson(route('panel.usuarios.index'))->assertStatus(401);
});
