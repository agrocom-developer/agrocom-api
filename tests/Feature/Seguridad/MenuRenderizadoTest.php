<?php

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-02, CA "menú renderizado desde sec_menu/sec_permission según el rol
 * activo" y CA "botones ocultos sin permiso", verificados sobre el HTML que
 * realmente sale del panel — no sobre los datos que el controlador le pasa a
 * la vista.
 *
 * `ObtenerMenuPorRolActivoTest` ya prueba el filtrado del árbol y
 * `PaginasPanelTest` que la vista recibe ese árbol; entre ambos quedaba un
 * hueco: nada garantizaba que el template no pintara igual un ítem filtrado
 * (los organisms del riel/sidebar/drawer reciben el árbol tres veces, y
 * cualquiera de los tres podría dejar escapar el link).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);
});

function menuRenderAsignarRol(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

it('el HTML del panel incluye el link de un ítem cuyo permiso tiene el rol activo', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = menuRenderAsignarRol($usuario, 'dueno'); // seguridad.usuario.ver

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->get(route('panel.dashboard'))
        ->assertOk()
        ->assertSee(route('panel.usuarios.index'));
});

it('el HTML del panel NO incluye el link de un ítem cuyo permiso le falta al rol activo', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = menuRenderAsignarRol($usuario, 'piloto'); // sin permisos de seguridad

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idPiloto]);

    $this->get(route('panel.dashboard'))
        ->assertOk()
        // La pantalla de organización no lleva permiso: sigue visible.
        ->assertSee(route('panel.organizacion.index'))
        ->assertDontSee(route('panel.usuarios.index'));
});

it('el mismo usuario ve el link con un rol activo y deja de verlo con el otro, sin volver a loguearse', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = menuRenderAsignarRol($usuario, 'piloto');
    $idDueno = menuRenderAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);
    $this->get(route('panel.dashboard'))->assertSee(route('panel.usuarios.index'));

    // Cambio de rol activo en caliente, misma sesión.
    $this->postJson(route('panel.rol-activo.actualizar'), ['id_role' => $idPiloto])->assertOk();

    $this->get(route('panel.dashboard'))
        ->assertOk()
        ->assertDontSee(route('panel.usuarios.index'));
});
