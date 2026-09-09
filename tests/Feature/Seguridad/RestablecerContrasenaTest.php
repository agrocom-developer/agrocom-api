<?php

use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/*
 * Tarea 66 — pantalla de "elegir contraseña nueva", llegada desde el enlace
 * del correo. Un token es de un solo uso y de un solo guard: el broker que
 * lo redime resuelve el usuario por SU provider (`usuarios_internos`/
 * `usuarios_cliente`), scoped por `type` — nunca cruza.
 */

uses(RefreshDatabase::class);

function usuarioInternoParaReset(array $overrides = []): SecUser
{
    return SecUser::factory()->create([...['password' => 'ViejaClave123'], ...$overrides]);
}

function usuarioPortalParaReset(array $overrides = []): SecUser
{
    return SecUser::factory()->create([...[
        'password' => 'ViejaClave123',
        'type' => TipoUsuario::Cliente,
        'persona_id' => null,
    ], ...$overrides]);
}

it('el formulario de restablecer renderiza con el token y el email de la URL', function () {
    $usuario = usuarioInternoParaReset(['email' => 'con.token@agrocom.example']);
    $token = Password::broker('interno')->createToken($usuario);

    $this->get(route('restablecer.form', ['token' => $token, 'email' => $usuario->email]))
        ->assertOk()
        ->assertSee($usuario->email);
});

it('fija la contraseña nueva con un token válido: el login viejo falla, el nuevo funciona, y el token deja de servir', function () {
    $usuario = usuarioInternoParaReset(['username' => 'con.reset.valido', 'email' => 'reset.valido@agrocom.example']);
    $token = Password::broker('interno')->createToken($usuario);

    $this->post(route('restablecer.store'), [
        'token' => $token,
        'email' => $usuario->email,
        'password' => 'NuevaClaveReset123',
        'password_confirmation' => 'NuevaClaveReset123',
    ])->assertRedirect(route('login.form'));

    expect(Hash::check('NuevaClaveReset123', $usuario->fresh()->password))->toBeTrue()
        ->and(Hash::check('ViejaClave123', $usuario->fresh()->password))->toBeFalse();

    // El token ya se borró: un segundo intento con el mismo token falla.
    $this->post(route('restablecer.store'), [
        'token' => $token,
        'email' => $usuario->email,
        'password' => 'OtraClaveMas123',
        'password_confirmation' => 'OtraClaveMas123',
    ])->assertRedirect(route('login.form'))->assertSessionHasErrors('email');

    expect(Hash::check('NuevaClaveReset123', $usuario->fresh()->password))->toBeTrue();
});

it('rechaza un token vencido con un mensaje legible', function () {
    $usuario = usuarioInternoParaReset(['email' => 'reset.vencido@agrocom.example']);
    $token = Password::broker('interno')->createToken($usuario);

    // `expire` está en minutos (60): un `created_at` de hace 2 horas ya venció.
    DB::table('password_reset_tokens')->where('email', $usuario->email)->update([
        'created_at' => now()->subHours(2),
    ]);

    $this->post(route('restablecer.store'), [
        'token' => $token,
        'email' => $usuario->email,
        'password' => 'NuevaClaveReset123',
        'password_confirmation' => 'NuevaClaveReset123',
    ])
        ->assertRedirect(route('login.form'))
        ->assertSessionHasErrors('email');

    expect(Hash::check('ViejaClave123', $usuario->fresh()->password))->toBeTrue();
});

it('rechaza un token que no existe', function () {
    $usuario = usuarioInternoParaReset(['email' => 'sin.token@agrocom.example']);

    $this->post(route('restablecer.store'), [
        'token' => 'token-inventado',
        'email' => $usuario->email,
        'password' => 'NuevaClaveReset123',
        'password_confirmation' => 'NuevaClaveReset123',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('ViejaClave123', $usuario->fresh()->password))->toBeTrue();
});

it('una cuenta bloqueada no puede restablecer aunque el token siga siendo válido', function () {
    $usuario = usuarioInternoParaReset(['email' => 'reset.bloqueado@agrocom.example']);
    $token = Password::broker('interno')->createToken($usuario);

    $usuario->state = false;
    $usuario->save();

    $this->post(route('restablecer.store'), [
        'token' => $token,
        'email' => $usuario->email,
        'password' => 'NuevaClaveReset123',
        'password_confirmation' => 'NuevaClaveReset123',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('ViejaClave123', $usuario->fresh()->password))->toBeTrue();
});

it('un token emitido para el guard interno no sirve en /portal/restablecer', function () {
    $interno = usuarioInternoParaReset(['email' => 'compartido@agrocom.example']);
    $token = Password::broker('interno')->createToken($interno);

    $this->post(route('portal.restablecer.store'), [
        'token' => $token,
        'email' => $interno->email,
        'password' => 'NuevaClaveReset123',
        'password_confirmation' => 'NuevaClaveReset123',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('ViejaClave123', $interno->fresh()->password))->toBeTrue();
});

it('restablece la contraseña de una cuenta de portal por su propio broker', function () {
    $cliente = usuarioPortalParaReset(['username' => 'cliente.con.reset', 'email' => 'cliente.reset@agrocom.example']);
    $token = Password::broker('cliente')->createToken($cliente);

    $this->post(route('portal.restablecer.store'), [
        'token' => $token,
        'email' => $cliente->email,
        'password' => 'NuevaClavePortal123',
        'password_confirmation' => 'NuevaClavePortal123',
    ])->assertRedirect(route('portal.login.form'));

    expect(Hash::check('NuevaClavePortal123', $cliente->fresh()->password))->toBeTrue();

    $this->postJson('/portal/login', ['username' => 'cliente.con.reset', 'password' => 'NuevaClavePortal123'])
        ->assertOk();
});

it('cierra las sesiones de otros dispositivos del usuario al restablecer', function () {
    config(['session.driver' => 'database']);

    $usuario = usuarioInternoParaReset(['email' => 'reset.otras.sesiones@agrocom.example']);
    $token = Password::broker('interno')->createToken($usuario);

    $claveLogin = Auth::guard('interno')->getName();
    $idOtroDispositivo = 'sesion-otro-dispositivo-reset';

    DB::table('sessions')->insert([
        'id' => $idOtroDispositivo,
        'user_id' => null,
        'payload' => base64_encode(json_encode([$claveLogin => $usuario->id])),
        'last_activity' => time(),
    ]);

    $this->post(route('restablecer.store'), [
        'token' => $token,
        'email' => $usuario->email,
        'password' => 'NuevaClaveReset123',
        'password_confirmation' => 'NuevaClaveReset123',
    ])->assertRedirect(route('login.form'));

    expect(DB::table('sessions')->where('id', $idOtroDispositivo)->exists())->toBeFalse();
});
