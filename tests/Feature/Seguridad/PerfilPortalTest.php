<?php

use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioCliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/*
 * Tarea 66 — mismo autoservicio que PerfilPropioTest.php, para el guard
 * `cliente`. Una cuenta de portal no tiene rol ni `rol.activo`: el único
 * gate es la sesión de portal (mismo criterio que el resto de `/portal/*`).
 */

uses(RefreshDatabase::class);

function cuentaPortalParaPerfil(string $username): SecUser
{
    return SecUser::factory()->create([
        'username' => $username,
        'password' => 'Secreta123',
        'type' => TipoUsuario::Cliente,
        'persona_id' => null,
    ]);
}

function entrarAlPortalParaPerfil(SecUser $usuario): void
{
    test()->actingAs(SecUsuarioCliente::query()->findOrFail($usuario->id), 'cliente');
}

it('el formulario de perfil del portal renderiza', function () {
    $usuario = cuentaPortalParaPerfil('cliente.perfil');
    entrarAlPortalParaPerfil($usuario);

    $this->get(route('portal.perfil.edit'))->assertOk();
});

it('actualiza nombre y email de la cuenta de portal', function () {
    $usuario = cuentaPortalParaPerfil('cliente.perfil.datos');
    entrarAlPortalParaPerfil($usuario);

    $this->put(route('portal.perfil.update'), [
        'name' => 'Cliente Editado',
        'email' => 'cliente.editado@agrocom.example',
    ])->assertRedirect(route('portal.perfil.edit'));

    $usuario->refresh();
    expect($usuario->name)->toBe('Cliente Editado')
        ->and($usuario->email)->toBe('cliente.editado@agrocom.example');
});

it('la contraseña actual incorrecta da 422', function () {
    $usuario = cuentaPortalParaPerfil('cliente.perfil.pw.mala');
    entrarAlPortalParaPerfil($usuario);

    $this->putJson(route('portal.perfil.update'), [
        'name' => $usuario->name,
        'password_actual' => 'incorrecta',
        'password' => 'NuevaSecreta123',
        'password_confirmation' => 'NuevaSecreta123',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password_actual');

    expect(Hash::check('Secreta123', $usuario->fresh()->password))->toBeTrue();
});

it('cambia la contraseña con la actual correcta: el login del portal viejo falla y el nuevo funciona', function () {
    $usuario = cuentaPortalParaPerfil('cliente.perfil.pw.buena');
    entrarAlPortalParaPerfil($usuario);

    $this->put(route('portal.perfil.update'), [
        'name' => $usuario->name,
        'password_actual' => 'Secreta123',
        'password' => 'NuevaSecreta123',
        'password_confirmation' => 'NuevaSecreta123',
    ])->assertRedirect(route('portal.perfil.edit'));

    $this->post('/portal/logout');

    $this->postJson('/portal/login', ['username' => 'cliente.perfil.pw.buena', 'password' => 'Secreta123'])
        ->assertStatus(422);

    $this->postJson('/portal/login', ['username' => 'cliente.perfil.pw.buena', 'password' => 'NuevaSecreta123'])
        ->assertOk();
});

it('el token de sesión de un cambio de contraseña del portal no toca cuentas internas', function () {
    config(['session.driver' => 'database']);

    $usuario = cuentaPortalParaPerfil('cliente.perfil.aislado');
    $interno = SecUser::factory()->create();

    $claveLoginInterno = Auth::guard('interno')->getName();
    $idSesionInterna = 'sesion-interna-ajena';

    DB::table('sessions')->insert([
        'id' => $idSesionInterna,
        'user_id' => null,
        'payload' => base64_encode(json_encode([$claveLoginInterno => $interno->id])),
        'last_activity' => time(),
    ]);

    entrarAlPortalParaPerfil($usuario);

    $this->put(route('portal.perfil.update'), [
        'name' => $usuario->name,
        'password_actual' => 'Secreta123',
        'password' => 'NuevaSecreta123',
        'password_confirmation' => 'NuevaSecreta123',
    ])->assertRedirect(route('portal.perfil.edit'));

    // El cambio de contraseña del portal cierra sesiones del guard `cliente`
    // — una sesión INTERNA (otro guard, otro usuario) no se toca.
    expect(DB::table('sessions')->where('id', $idSesionInterna)->exists())->toBeTrue();
});
