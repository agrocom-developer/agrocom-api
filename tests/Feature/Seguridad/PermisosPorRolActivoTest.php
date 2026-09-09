<?php

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-02 — invariante 10 de CLAUDE.md, no negociable: "los permisos efectivos
 * son los del rol activo, nunca la unión de todos los roles del usuario".
 * `tienePermiso()` (unión) sigue existiendo tal cual para el único llamador
 * sin sesión (`AsignarRolesUsuario`, HU-01); esta suite fija el contraste
 * con `tienePermisoEnRol()` (ADR 0004, extensión 27/8/2026, punto 5,
 * alternativa (b)).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
});

function permisosAsignarRol(SecUser $usuario, int $idRol): void
{
    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();
}

it('tienePermisoEnRol evalúa solo el rol activo pasado, nunca la unión de todos los roles', function () {
    $usuario = SecUser::factory()->create();

    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id'); // sin permisos de seguridad
    $idDueno = (int) SecRole::query()->where('name', 'dueno')->value('id'); // seguridad.usuario.ver, entre otros

    permisosAsignarRol($usuario, $idPiloto);
    permisosAsignarRol($usuario, $idDueno);

    // Unión (sin sesión, uso legítimo de AsignarRolesUsuario): sí, por dueño.
    expect($usuario->tienePermiso('seguridad.usuario.ver'))->toBeTrue()
        // Rol activo = dueño: sí.
        ->and($usuario->tienePermisoEnRol('seguridad.usuario.ver', $idDueno))->toBeTrue()
        // Rol activo = piloto, aunque el usuario también tenga dueño asignado: no.
        ->and($usuario->tienePermisoEnRol('seguridad.usuario.ver', $idPiloto))->toBeFalse();
});

it('tienePermisoEnRol ignora permisos de un rol desactivado a nivel de catálogo', function () {
    $usuario = SecUser::factory()->create();
    $idDueno = (int) SecRole::query()->where('name', 'dueno')->value('id');

    permisosAsignarRol($usuario, $idDueno);

    SecRole::query()->whereKey($idDueno)->update(['state' => false]);

    expect($usuario->tienePermisoEnRol('seguridad.usuario.ver', $idDueno))->toBeFalse();
});

it('tienePermisoEnRol es false para un rol que ni siquiera existe', function () {
    $usuario = SecUser::factory()->create();

    expect($usuario->tienePermisoEnRol('seguridad.usuario.ver', 999999))->toBeFalse();
});

it('un encargado de operaciones activo no puede asignar_rol_dueno aunque en otro rol asignado sí pudiera', function () {
    $usuario = SecUser::factory()->create();

    $idEncargado = (int) SecRole::query()->where('name', 'encargado_operaciones')->value('id');
    $idDueno = (int) SecRole::query()->where('name', 'dueno')->value('id');

    permisosAsignarRol($usuario, $idEncargado);
    permisosAsignarRol($usuario, $idDueno);

    expect($usuario->tienePermisoEnRol('seguridad.usuario.asignar_rol_dueno', $idEncargado))->toBeFalse()
        ->and($usuario->tienePermisoEnRol('seguridad.usuario.asignar_rol_dueno', $idDueno))->toBeTrue();
});
