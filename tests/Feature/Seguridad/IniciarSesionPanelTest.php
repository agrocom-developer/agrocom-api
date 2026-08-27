<?php

use App\Dominios\Seguridad\Aplicacion\ElegirRolActivo;
use App\Dominios\Seguridad\Aplicacion\IniciarSesionPanel;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

/*
 * HU-02 — IniciarSesionPanel: qué pasa con el rol activo apenas se autentica
 * una cuenta del panel (ADR 0004, extensión 27/8/2026, punto 3). La
 * verificación de username+password es responsabilidad del controlador
 * (Auth::attempt); acá se prueba la resolución de rol activo aislada de HTTP.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->caso = new IniciarSesionPanel(new ElegirRolActivo);
});

/** @param  list<string>  $nombresDeRol */
function iniciarSesionAsignarRoles(SecUser $usuario, array $nombresDeRol): void
{
    foreach ($nombresDeRol as $nombre) {
        $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

        $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
        $pivote->created_by = $usuario->id;
        $pivote->updated_by = $usuario->id;
        $pivote->save();
    }
}

it('activa el único rol vivo automáticamente, sin pedir selección', function () {
    $usuario = SecUser::factory()->create();
    iniciarSesionAsignarRoles($usuario, ['piloto']);

    $resultado = $this->caso->ejecutar($usuario);

    expect($resultado->requiereSeleccion)->toBeFalse()
        ->and($resultado->rolActivo?->name)->toBe('piloto')
        ->and(Session::get('sec_rol_activo_id'))->toBe($resultado->rolActivo->id);
});

it('pide selección cuando el usuario tiene más de un rol vivo, sin fijar ninguno', function () {
    $usuario = SecUser::factory()->create();
    iniciarSesionAsignarRoles($usuario, ['piloto', 'auxiliar']);

    $resultado = $this->caso->ejecutar($usuario);

    expect($resultado->requiereSeleccion)->toBeTrue()
        ->and($resultado->rolActivo)->toBeNull()
        ->and($resultado->rolesDisponibles->pluck('name')->sort()->values()->all())
        ->toBe(['auxiliar', 'piloto'])
        ->and(Session::get('sec_rol_activo_id'))->toBeNull();
});

it('pide selección con lista vacía cuando el usuario no tiene ningún rol vivo', function () {
    $usuario = SecUser::factory()->create();

    $resultado = $this->caso->ejecutar($usuario);

    expect($resultado->requiereSeleccion)->toBeTrue()
        ->and($resultado->rolesDisponibles)->toBeEmpty();
});

it('descarta un rol activo obsoleto de una sesión previa si ahora tiene varios roles vivos', function () {
    $usuario = SecUser::factory()->create();
    iniciarSesionAsignarRoles($usuario, ['piloto', 'auxiliar']);
    Session::put('sec_rol_activo_id', 999999);

    $this->caso->ejecutar($usuario);

    expect(Session::get('sec_rol_activo_id'))->toBeNull();
});

it('un rol desactivado a nivel de catálogo no cuenta: si solo queda uno vivo, se autoactiva', function () {
    $usuario = SecUser::factory()->create();
    iniciarSesionAsignarRoles($usuario, ['piloto', 'auxiliar']);

    SecRole::query()->where('name', 'auxiliar')->update(['state' => false]);

    $resultado = $this->caso->ejecutar($usuario);

    // Antes de contar "roles activos" (no solo "asignados"), esto hubiera
    // intentado activar un rol desactivado y explotado con RolNoAsignado en
    // vez de resolver el único rol realmente usable.
    expect($resultado->requiereSeleccion)->toBeFalse()
        ->and($resultado->rolActivo?->name)->toBe('piloto');
});

it('no ofrece roles desactivados a nivel de catálogo como disponibles al pedir selección', function () {
    $usuario = SecUser::factory()->create();
    iniciarSesionAsignarRoles($usuario, ['piloto', 'auxiliar', 'jefe_campo']);

    SecRole::query()->where('name', 'auxiliar')->update(['state' => false]);

    $resultado = $this->caso->ejecutar($usuario);

    expect($resultado->requiereSeleccion)->toBeTrue()
        ->and($resultado->rolesDisponibles->pluck('name')->sort()->values()->all())
        ->toBe(['jefe_campo', 'piloto']);
});
