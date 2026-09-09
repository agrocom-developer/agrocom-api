<?php

use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-02 — POST /login: autenticación del panel por `username` + password
 * (nunca correo, memoria del proyecto) contra el guard `interno`, con
 * resolución de rol activo (ADR 0004, extensión 27/8/2026, punto 3).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
});

function loginAsignarRol(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

it('inicia sesión por username y activa automáticamente el único rol asignado', function () {
    $usuario = SecUser::factory()->create(['username' => 'jperez', 'password' => 'Secreta123']);
    $idPiloto = loginAsignarRol($usuario, 'piloto');

    $respuesta = $this->postJson('/login', ['username' => 'jperez', 'password' => 'Secreta123']);

    $respuesta->assertOk()->assertJson([
        'requiere_seleccion_rol' => false,
        'rol_activo_id' => $idPiloto,
    ]);

    expect(session('sec_rol_activo_id'))->toBe($idPiloto);
    $this->assertAuthenticatedAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno');
});

it('pide seleccionar rol cuando el usuario tiene más de uno asignado, sin fijar ninguno', function () {
    $usuario = SecUser::factory()->create(['username' => 'multi', 'password' => 'Secreta123']);
    loginAsignarRol($usuario, 'piloto');
    loginAsignarRol($usuario, 'auxiliar');

    $respuesta = $this->postJson('/login', ['username' => 'multi', 'password' => 'Secreta123']);

    $respuesta->assertOk()
        ->assertJson(['requiere_seleccion_rol' => true, 'rol_activo_id' => null])
        ->assertJsonCount(2, 'roles');

    expect(session('sec_rol_activo_id'))->toBeNull();
});

it('responde requiere_seleccion_rol con lista vacía si el usuario no tiene ningún rol asignado', function () {
    $usuario = SecUser::factory()->create(['username' => 'sinroles', 'password' => 'Secreta123']);

    $respuesta = $this->postJson('/login', ['username' => 'sinroles', 'password' => 'Secreta123']);

    $respuesta->assertOk()
        ->assertJson(['requiere_seleccion_rol' => true])
        ->assertJsonCount(0, 'roles');
});

it('rechaza credenciales inválidas con 422 y no autentica', function () {
    SecUser::factory()->create(['username' => 'jperez', 'password' => 'Secreta123']);

    $respuesta = $this->postJson('/login', ['username' => 'jperez', 'password' => 'incorrecta']);

    $respuesta->assertStatus(422);
    $this->assertGuest('interno');
});

it('exige username y password', function () {
    $this->postJson('/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['username', 'password']);
});

/*
 * Tarea 63 — el login fija la zona horaria IANA que declara el navegador
 * (campo oculto `zona_horaria`), SOLO la primera vez.
 */
it('fija la zona horaria declarada por el navegador en el primer login', function () {
    $usuario = SecUser::factory()->create(['username' => 'jperez', 'password' => 'Secreta123']);
    loginAsignarRol($usuario, 'piloto');

    $this->postJson('/login', [
        'username' => 'jperez',
        'password' => 'Secreta123',
        'zona_horaria' => 'America/La_Paz',
    ])->assertOk();

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->value('zona_horaria'))->toBe('America/La_Paz');
});

it('no pisa una zona horaria ya elegida en un login posterior', function () {
    $usuario = SecUser::factory()->create(['username' => 'jperez', 'password' => 'Secreta123']);
    loginAsignarRol($usuario, 'piloto');
    SecUserPreferencia::query()->create(['user_id' => $usuario->id, 'zona_horaria' => 'Europe/Madrid']);

    $this->postJson('/login', [
        'username' => 'jperez',
        'password' => 'Secreta123',
        'zona_horaria' => 'America/La_Paz',
    ])->assertOk();

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->value('zona_horaria'))->toBe('Europe/Madrid');
});

it('el login funciona igual sin campo de zona horaria (navegador sin soporte de Intl)', function () {
    $usuario = SecUser::factory()->create(['username' => 'jperez', 'password' => 'Secreta123']);
    loginAsignarRol($usuario, 'piloto');

    // La fila de preferencia igual puede existir (ElegirRolActivo registra
    // `ultimo_rol_id` con un solo rol asignado) — lo que importa acá es que
    // `zona_horaria` nunca se inventa un valor sin dato del navegador.
    $this->postJson('/login', ['username' => 'jperez', 'password' => 'Secreta123'])->assertOk();

    expect(SecUserPreferencia::query()->where('user_id', $usuario->id)->value('zona_horaria'))->toBeNull();
});

it('no autentica cuentas de tipo cliente contra el guard interno', function () {
    SecUser::factory()->create([
        'username' => 'cliente.demo',
        'password' => 'Secreta123',
        'type' => TipoUsuario::Cliente,
    ]);

    $respuesta = $this->postJson('/login', ['username' => 'cliente.demo', 'password' => 'Secreta123']);

    $respuesta->assertStatus(422);
    $this->assertGuest('interno');
});
