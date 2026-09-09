<?php

use App\Dominios\Seguridad\Aplicacion\ElegirRolActivo;
use App\Dominios\Seguridad\Dominio\Excepciones\RolNoAsignado;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

/*
 * HU-02 — ElegirRolActivo: único punto que escribe
 * session('sec_rol_activo_id') (ADR 0004, extensión 27/8/2026, puntos 2 y
 * 4). Revalida siempre contra sec_user_role/sec_role, nunca confía en el
 * valor recibido.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->caso = new ElegirRolActivo;
});

function rolActivoAsignar(SecUser $usuario, string $nombreRol): SecUserRole
{
    $idRol = (int) SecRole::query()->where('name', $nombreRol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $pivote;
}

it('fija en sesión el rol elegido cuando está vivo y asignado al usuario', function () {
    $usuario = SecUser::factory()->create();
    $pivote = rolActivoAsignar($usuario, 'piloto');

    $rol = $this->caso->ejecutar($usuario, $pivote->id_role);

    expect($rol->id)->toBe($pivote->id_role)
        ->and($rol->name)->toBe('piloto')
        ->and(Session::get('sec_rol_activo_id'))->toBe($pivote->id_role);
});

it('rechaza un rol que el usuario nunca tuvo asignado', function () {
    $usuario = SecUser::factory()->create();
    rolActivoAsignar($usuario, 'piloto');
    $idDueno = (int) SecRole::query()->where('name', 'dueno')->value('id');

    expect(fn () => $this->caso->ejecutar($usuario, $idDueno))->toThrow(RolNoAsignado::class);
    expect(Session::get('sec_rol_activo_id'))->toBeNull();
});

it('rechaza un rol que le fue revocado al usuario (soft delete del pivote)', function () {
    $usuario = SecUser::factory()->create();
    $pivote = rolActivoAsignar($usuario, 'piloto');
    $idPiloto = $pivote->id_role;

    $pivote->delete();

    expect(fn () => $this->caso->ejecutar($usuario, $idPiloto))->toThrow(RolNoAsignado::class);
});

it('rechaza un rol desactivado a nivel de catálogo aunque siga asignado', function () {
    $usuario = SecUser::factory()->create();
    $pivote = rolActivoAsignar($usuario, 'piloto');
    $idPiloto = $pivote->id_role;

    SecRole::query()->whereKey($idPiloto)->update(['state' => false]);

    expect(fn () => $this->caso->ejecutar($usuario, $idPiloto))->toThrow(RolNoAsignado::class);
});

it('permite cambiar entre dos roles vivos distintos del mismo usuario', function () {
    $usuario = SecUser::factory()->create();
    $piloto = rolActivoAsignar($usuario, 'piloto');
    $auxiliar = rolActivoAsignar($usuario, 'auxiliar');

    $this->caso->ejecutar($usuario, $piloto->id_role);
    expect(Session::get('sec_rol_activo_id'))->toBe($piloto->id_role);

    $this->caso->ejecutar($usuario, $auxiliar->id_role);
    expect(Session::get('sec_rol_activo_id'))->toBe($auxiliar->id_role);
});
