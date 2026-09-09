<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Tarea 63 — GET /panel/bitacora: pantalla de auditoría, solo `dueno` y
 * `encargado_operaciones` (permiso `seguridad.bitacora.ver`). Solo lectura
 * por definición (ADR 0007) — ninguna otra verbo HTTP existe para esta ruta.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->seed(SecMenuSeeder::class);
});

function bitacoraAsignarRol(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

it('responde 200 para dueno', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = bitacoraAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->get(route('panel.bitacora.index'))
        ->assertOk()
        ->assertViewIs('seguridad::pages.bitacora.index');
});

it('responde 200 para encargado_operaciones', function () {
    $usuario = SecUser::factory()->create();
    $idRol = bitacoraAsignarRol($usuario, 'encargado_operaciones');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idRol]);

    $this->get(route('panel.bitacora.index'))->assertOk();
});

it('un rol sin seguridad.bitacora.ver recibe 403 y no ve el ítem de menú', function () {
    $usuario = SecUser::factory()->create();
    $idJefeCampo = bitacoraAsignarRol($usuario, 'jefe_campo');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idJefeCampo]);

    $this->get(route('panel.bitacora.index'))->assertStatus(403);

    $respuestaDashboard = $this->get(route('panel.dashboard'))->assertOk();
    $labelsVisibles = collect($respuestaDashboard->viewData('menu'))
        ->flatMap(fn ($modulo) => collect($modulo->hijos)->pluck('label'))
        ->all();

    expect($labelsVisibles)->not->toContain('menu.seguridad.items.bitacora');
});

it('dueno sí ve el ítem de menú de bitácora', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = bitacoraAsignarRol($usuario, 'dueno');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $respuesta = $this->get(route('panel.dashboard'))->assertOk();
    $labelsVisibles = collect($respuesta->viewData('menu'))
        ->flatMap(fn ($modulo) => collect($modulo->hijos)->pluck('label'))
        ->all();

    expect($labelsVisibles)->toContain('menu.seguridad.items.bitacora');
});

it('exige autenticación', function () {
    $this->getJson(route('panel.bitacora.index'))->assertStatus(401);
});

it('el filtro por registro_id y tabla en el query string devuelve solo ese historial', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = bitacoraAsignarRol($usuario, 'dueno');

    // IDs altos a propósito: `SeguridadSeeder` (corrido en `beforeEach`) ya
    // deja sus propias filas de bitácora para `sec_role` con `registro_id`
    // 1-5 (los cinco roles del catálogo) — un id bajo colisionaría con esas
    // y falsearía el conteo.
    Bitacora::query()->create(['tabla' => 'sec_role', 'registro_id' => 501, 'accion' => AccionBitacora::Creado, 'despues' => ['name' => 'x']]);
    Bitacora::query()->create(['tabla' => 'sec_role', 'registro_id' => 502, 'accion' => AccionBitacora::Creado, 'despues' => ['name' => 'y']]);

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $respuesta = $this->get(route('panel.bitacora.index', ['tabla' => 'sec_role', 'registro_id' => 501]))
        ->assertOk();

    $filas = $respuesta->viewData('bitacora');

    expect($filas->total())->toBe(1)
        ->and($filas->items()[0]->registroId)->toBe(501);
});

it('ninguna ruta acepta PUT/DELETE sobre bitácora', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = bitacoraAsignarRol($usuario, 'dueno');
    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idDueno]);

    $this->put(route('panel.bitacora.index'))->assertStatus(405);
    $this->delete(route('panel.bitacora.index'))->assertStatus(405);
    $this->post(route('panel.bitacora.index'))->assertStatus(405);
});
