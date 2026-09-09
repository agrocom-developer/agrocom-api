<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/*
 * Tarea 66 — autoservicio del guard `interno`: `/panel/perfil` administra
 * SIEMPRE al propio usuario autenticado, con cualquier rol activo y sin
 * permiso de grano fino (a diferencia de GestionUsuariosPanelTest, que
 * administra cuentas ajenas). Mismo molde de fixtures que esa suite.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

/** Usuario con un único rol vivo — mismo criterio que ResolverRolActivo para auto-activarlo. */
function usuarioConRolParaPerfil(string $username, string $rol, array $overrides = []): array
{
    $usuario = SecUser::factory()->create([...['username' => $username, 'password' => 'Secreta123'], ...$overrides]);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaPerfil(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

it('el formulario de perfil renderiza para cualquier usuario logueado, sin permiso especial', function () {
    // "Piloto" es el rol con menos permisos del catálogo — si esto anda con
    // ese rol, anda con cualquiera.
    [$usuario, $idRol] = usuarioConRolParaPerfil('piloto.perfil', 'piloto');
    entrarAlPanelParaPerfil($usuario, $idRol);

    $this->get(route('panel.perfil.edit'))->assertOk();
});

it('actualiza el email sin tocar la contraseña', function () {
    [$usuario, $idRol] = usuarioConRolParaPerfil('con.datos', 'piloto');
    entrarAlPanelParaPerfil($usuario, $idRol);

    $this->put(route('panel.perfil.update'), [
        'email' => 'editado@agrocom.example',
    ])->assertRedirect(route('panel.perfil.edit'));

    $usuario->refresh();
    expect($usuario->email)->toBe('editado@agrocom.example')
        ->and(Hash::check('Secreta123', $usuario->password))->toBeTrue();
});

// El nombre del actor lo resuelve `ListarBitacora` por join contra el valor
// VIGENTE de `sec_user`, así que renombrarse reescribía la autoría de toda la
// historia. Desde el 9/9/2026 lo cambia un administrador desde Seguridad ›
// Usuarios. El `readonly` de la pantalla no defiende nada por sí solo: lo que
// vale es que el POST directo tampoco pueda.
it('ignora el nombre aunque venga en el POST', function () {
    [$usuario, $idRol] = usuarioConRolParaPerfil('con.nombre.fijo', 'piloto', ['name' => 'Nombre Original']);
    entrarAlPanelParaPerfil($usuario, $idRol);

    $this->put(route('panel.perfil.update'), [
        'name' => 'Nombre Colado Por POST',
        'email' => 'sigue@agrocom.example',
    ])->assertRedirect(route('panel.perfil.edit'));

    $usuario->refresh();
    expect($usuario->name)->toBe('Nombre Original')
        ->and($usuario->email)->toBe('sigue@agrocom.example');
});

it('no ofrece el nombre como campo editable en la pantalla', function () {
    [$usuario, $idRol] = usuarioConRolParaPerfil('mira.su.perfil', 'piloto', ['name' => 'Nombre Original']);
    entrarAlPanelParaPerfil($usuario, $idRol);

    $respuesta = $this->get(route('panel.perfil.edit'));

    $respuesta->assertOk()
        ->assertSee('Nombre Original')
        ->assertDontSee('name="name"', false);
});

it('rechaza un email ya usado por otra cuenta viva', function () {
    SecUser::factory()->create(['email' => 'ocupado@agrocom.example']);
    [$usuario, $idRol] = usuarioConRolParaPerfil('con.email.propio', 'piloto');
    entrarAlPanelParaPerfil($usuario, $idRol);

    $this->put(route('panel.perfil.update'), [
        'email' => 'ocupado@agrocom.example',
    ])->assertSessionHasErrors('email');

    expect($usuario->fresh()?->email)->not->toBe('ocupado@agrocom.example');
});

it('la contraseña actual incorrecta da 422 y no cambia nada', function () {
    [$usuario, $idRol] = usuarioConRolParaPerfil('con.pw.incorrecta', 'piloto');
    entrarAlPanelParaPerfil($usuario, $idRol);

    $this->putJson(route('panel.perfil.update'), [
        'password_actual' => 'incorrecta',
        'password' => 'NuevaSecreta123',
        'password_confirmation' => 'NuevaSecreta123',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password_actual');

    expect(Hash::check('Secreta123', $usuario->fresh()->password))->toBeTrue();
});

it('la contraseña actual correcta cambia la contraseña: el login viejo falla y el nuevo funciona', function () {
    [$usuario, $idRol] = usuarioConRolParaPerfil('con.password.vieja', 'piloto');
    entrarAlPanelParaPerfil($usuario, $idRol);

    $this->put(route('panel.perfil.update'), [
        'password_actual' => 'Secreta123',
        'password' => 'NuevaSecreta123',
        'password_confirmation' => 'NuevaSecreta123',
    ])->assertRedirect(route('panel.perfil.edit'));

    $this->post('/logout');

    $this->postJson('/login', ['username' => 'con.password.vieja', 'password' => 'Secreta123'])
        ->assertStatus(422);

    $this->postJson('/login', ['username' => 'con.password.vieja', 'password' => 'NuevaSecreta123'])
        ->assertOk();
});

it('cambiar la contraseña cierra las sesiones de otros dispositivos, sin tocar la que hizo el cambio', function () {
    config(['session.driver' => 'database']);

    [$usuario, $idRol] = usuarioConRolParaPerfil('con.otro.dispositivo', 'piloto');

    $claveLogin = Auth::guard('interno')->getName();
    $idOtroDispositivo = 'sesion-otro-dispositivo';

    DB::table('sessions')->insert([
        'id' => $idOtroDispositivo,
        'user_id' => null,
        'ip_address' => '10.0.0.9',
        'user_agent' => 'otro-dispositivo',
        'payload' => base64_encode(json_encode([$claveLogin => $usuario->id])),
        'last_activity' => time(),
    ]);

    entrarAlPanelParaPerfil($usuario, $idRol);

    $this->put(route('panel.perfil.update'), [
        'password_actual' => 'Secreta123',
        'password' => 'NuevaSecreta123',
        'password_confirmation' => 'NuevaSecreta123',
    ])->assertRedirect(route('panel.perfil.edit'));

    expect(DB::table('sessions')->where('id', $idOtroDispositivo)->exists())->toBeFalse();
});

it('no cierra sesiones de otro usuario ni de nadie si no se pidió cambio de contraseña', function () {
    config(['session.driver' => 'database']);

    [$usuario, $idRol] = usuarioConRolParaPerfil('sin.cambio.pw', 'piloto');
    $otro = SecUser::factory()->create();

    $claveLogin = Auth::guard('interno')->getName();
    $idSesionOtroUsuario = 'sesion-otro-usuario';

    DB::table('sessions')->insert([
        'id' => $idSesionOtroUsuario,
        'user_id' => null,
        'payload' => base64_encode(json_encode([$claveLogin => $otro->id])),
        'last_activity' => time(),
    ]);

    entrarAlPanelParaPerfil($usuario, $idRol);

    $this->put(route('panel.perfil.update'), [
        'email' => 'sigue.igual@agrocom.example',
    ])->assertRedirect(route('panel.perfil.edit'));

    expect(DB::table('sessions')->where('id', $idSesionOtroUsuario)->exists())->toBeTrue();
});

it('la bitácora del cambio de contraseña no guarda ningún hash', function () {
    [$usuario, $idRol] = usuarioConRolParaPerfil('con.bitacora', 'piloto');
    entrarAlPanelParaPerfil($usuario, $idRol);

    $this->put(route('panel.perfil.update'), [
        'password_actual' => 'Secreta123',
        'password' => 'NuevaSecreta123',
        'password_confirmation' => 'NuevaSecreta123',
    ]);

    $fila = Bitacora::query()
        ->where('tabla', 'sec_user')
        ->where('registro_id', $usuario->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->latest('id')
        ->first();

    expect($fila)->not->toBeNull()
        ->and($fila->antes)->not->toHaveKey('password')
        ->and($fila->despues)->not->toHaveKey('password');
});
