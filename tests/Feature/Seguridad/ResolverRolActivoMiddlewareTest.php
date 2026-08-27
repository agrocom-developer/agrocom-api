<?php

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/*
 * HU-02 — middleware ResolverRolActivo (ADR 0004, extensión 27/8/2026,
 * punto 2). Se registra una ruta efímera solo para este test: HU-02 backend
 * no incluye ninguna pantalla protegida del panel todavía (eso es
 * Blade/Livewire, fuera de alcance de este agente) — el contrato del
 * middleware es independiente de qué ruta real lo use más adelante.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);

    Route::middleware(['web', 'auth:interno', ResolverRolActivo::class])
        ->get('/_test/panel', fn () => response()->json([
            'ok' => true,
            'rol_activo_id' => session('sec_rol_activo_id'),
        ]));
});

function middlewareAsignarRol(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

it('deja pasar el request cuando el rol activo de sesión sigue vivo', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = middlewareAsignarRol($usuario, 'piloto');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idPiloto]);

    $this->getJson('/_test/panel')
        ->assertOk()
        ->assertJson(['rol_activo_id' => $idPiloto]);
});

it('autofija el único rol vivo si la sesión llega sin rol activo resuelto', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = middlewareAsignarRol($usuario, 'piloto');

    $this->actingAs($usuario, 'interno');

    $this->getJson('/_test/panel')
        ->assertOk()
        ->assertJson(['rol_activo_id' => $idPiloto]);
});

it('responde 409 con la lista de roles si hay más de uno vivo y ninguno activo resuelto', function () {
    $usuario = SecUser::factory()->create();
    middlewareAsignarRol($usuario, 'piloto');
    middlewareAsignarRol($usuario, 'auxiliar');

    $this->actingAs($usuario, 'interno');

    $this->getJson('/_test/panel')
        ->assertStatus(409)
        ->assertJsonCount(2, 'roles');
});

it('revalida contra la base en cada request: un rol revocado a mitad de sesión deja de dar acceso', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = middlewareAsignarRol($usuario, 'piloto');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idPiloto]);

    $this->getJson('/_test/panel')->assertOk();

    // Se lo revocan a mitad de sesión (soft delete del pivote) — no un
    // UPDATE ni un DELETE físico.
    SecUserRole::query()->where('id_user', $usuario->id)->where('id_role', $idPiloto)->firstOrFail()->delete();

    $this->getJson('/_test/panel')->assertStatus(409);
    expect(session('sec_rol_activo_id'))->toBeNull();
});

it('un rol desactivado a nivel de catálogo también invalida el rol activo de sesión', function () {
    $usuario = SecUser::factory()->create();
    $idPiloto = middlewareAsignarRol($usuario, 'piloto');

    $this->actingAs($usuario, 'interno')->withSession(['sec_rol_activo_id' => $idPiloto]);

    SecRole::query()->whereKey($idPiloto)->update(['state' => false]);

    $this->getJson('/_test/panel')->assertStatus(409);
});

it('sin roles vivos responde 409 con lista vacía, nunca un fallback a permitir todo', function () {
    $usuario = SecUser::factory()->create();

    $this->actingAs($usuario, 'interno');

    $this->getJson('/_test/panel')
        ->assertStatus(409)
        ->assertJsonCount(0, 'roles');
});

it('sin autenticación responde 401 antes de llegar al middleware de rol activo', function () {
    $this->getJson('/_test/panel')->assertStatus(401);
});
