<?php

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-02 — POST /panel/rol-activo: cambio de rol activo sin volver a
 * loguearse (ADR 0004, extensión 27/8/2026, punto 4). Ruta deliberadamente
 * sin el middleware ResolverRolActivo (es la vía de escape para resolver el
 * rol activo la primera vez).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
});

function cambiarRolAsignar(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

it('cambia el rol activo sin volver a loguearse cuando el rol destino está asignado', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = cambiarRolAsignar($usuario, 'piloto');
    $idAuxiliar = cambiarRolAsignar($usuario, 'auxiliar');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idPiloto]);

    $respuesta = $this->postJson('/panel/rol-activo', ['id_role' => $idAuxiliar]);

    $respuesta->assertOk()->assertJson(['rol_activo_id' => $idAuxiliar, 'rol_activo_nombre' => 'auxiliar']);
    expect(session('sec_rol_activo_id'))->toBe($idAuxiliar);
});

it('permite elegir el primer rol activo de la sesión (selector tras un login con 2+ roles)', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = cambiarRolAsignar($usuario, 'piloto');
    cambiarRolAsignar($usuario, 'auxiliar');

    $this->actingAs($usuario, 'interno');
    expect(session('sec_rol_activo_id'))->toBeNull();

    $respuesta = $this->postJson('/panel/rol-activo', ['id_role' => $idPiloto]);

    $respuesta->assertOk();
    expect(session('sec_rol_activo_id'))->toBe($idPiloto);
});

it('rechaza con 403 un rol que el usuario no tiene asignado, sin tocar el rol activo previo', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = cambiarRolAsignar($usuario, 'piloto');
    $idDueno = (int) SecRole::query()->where('name', 'dueno')->value('id');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idPiloto]);

    $respuesta = $this->postJson('/panel/rol-activo', ['id_role' => $idDueno]);

    $respuesta->assertStatus(403);
    expect(session('sec_rol_activo_id'))->toBe($idPiloto);
});

it('rechaza un rol revocado (soft delete del pivote) aunque haya sido válido antes', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = cambiarRolAsignar($usuario, 'piloto');

    SecUserRole::query()->where('id_user', $usuario->id)->where('id_role', $idPiloto)->firstOrFail()->delete();

    $this->actingAs($usuario, 'interno');

    $this->postJson('/panel/rol-activo', ['id_role' => $idPiloto])->assertStatus(403);
});

it('exige autenticación', function () {
    $this->postJson('/panel/rol-activo', ['id_role' => 1])->assertStatus(401);
});

it('exige id_role numérico', function () {
    $usuario = SecUser::factory()->create();
    $this->actingAs($usuario, 'interno');

    $this->postJson('/panel/rol-activo', ['id_role' => 'no-numerico'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['id_role']);
});
