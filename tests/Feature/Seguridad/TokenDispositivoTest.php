<?php

use App\Dominios\Compartido\Dominio\Excepciones\BorradoFisicoNoPermitido;
use App\Dominios\Seguridad\Aplicacion\EmitirTokenDispositivo;
use App\Dominios\Seguridad\Dominio\Excepciones\EmisionDirectaDeTokenNoPermitida;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/*
 * HU-03 — token Sanctum por dispositivo para la app de campo.
 *
 * CA cubiertos acá: "token Sanctum por dispositivo" (emisión, formato,
 * autenticación, un solo token vivo por equipo) y "sesión persistente
 * offline" (no caduca por tiempo). La revocación desde el panel vive en
 * RevocarDispositivoPanelTest; el scoping, en ScopingDispositivosTest.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
});

const UUID_EQUIPO = '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31';

function asignarRol(SecUser $usuario, string $nombre): int
{
    $idRol = (int) SecRole::query()->where('name', $nombre)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return $idRol;
}

/** Piloto con un único rol vivo: el caso normal de la app de campo. */
function pilotoDeCampo(array $atributos = []): SecUser
{
    $usuario = SecUser::factory()->create([
        'username' => 'jperez',
        'password' => 'Secreta123',
        ...$atributos,
    ]);

    asignarRol($usuario, 'piloto');

    return $usuario;
}

function emitirToken(array $payload = []): string
{
    $respuesta = test()->postJson('/api/auth/token', [
        'username' => 'jperez',
        'password' => 'Secreta123',
        'uuid_dispositivo' => UUID_EQUIPO,
        ...$payload,
    ]);

    $respuesta->assertCreated();

    return (string) $respuesta->json('token');
}

it('emite un token para el dispositivo y lo deja registrado con su rol activo', function () {
    $usuario = pilotoDeCampo();
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');

    $respuesta = $this->postJson('/api/auth/token', [
        'username' => 'jperez',
        'password' => 'Secreta123',
        'uuid_dispositivo' => UUID_EQUIPO,
        'nombre_dispositivo' => 'Moto G84 — piloto 2',
    ]);

    $respuesta->assertCreated()
        ->assertJsonStructure([
            'token',
            'token_type',
            'usuario' => ['id', 'name', 'username', 'persona_id'],
            'rol' => ['id', 'name', 'description'],
            'dispositivo' => ['id', 'uuid_dispositivo', 'nombre_dispositivo', 'rol', 'last_used_at', 'created_at'],
        ])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('rol.id', $idPiloto)
        ->assertJsonPath('dispositivo.uuid_dispositivo', UUID_EQUIPO);

    $token = SecTokenDispositivo::query()->sole();

    expect($token->user_id)->toBe($usuario->id)
        ->and($token->role_id)->toBe($idPiloto)
        ->and($token->uuid_dispositivo)->toBe(UUID_EQUIPO)
        ->and($token->nombre_dispositivo)->toBe('Moto G84 — piloto 2')
        // Autoría explícita: en el login todavía no hay usuario autenticado,
        // así que RegistraAutoria no tiene de dónde sacarla.
        ->and($token->created_by)->toBe($usuario->id)
        // El token no caduca por tiempo (sesión persistente offline).
        ->and($token->expires_at)->toBeNull();
});

it('guarda solo el hash del token, nunca el valor en claro', function () {
    pilotoDeCampo();

    $tokenPlano = emitirToken();
    [, $secreto] = explode('|', $tokenPlano, 2);

    $token = SecTokenDispositivo::query()->sole();

    expect($token->token)->toBe(hash('sha256', $secreto))
        ->and($token->token)->not->toContain($secreto)
        // El resource nunca lo devuelve: si lo hiciera, un token robado
        // sería una llave maestra de la cuenta.
        ->and($token->toArray())->not->toHaveKey('token');
});

it('autentica los endpoints de la app de campo con el token del dispositivo', function () {
    pilotoDeCampo();
    $tokenPlano = emitirToken();

    comoDispositivo($tokenPlano)
        ->getJson('/api/auth/sesion')
        ->assertOk()
        ->assertJsonPath('usuario.username', 'jperez')
        ->assertJsonPath('rol.name', 'piloto');
});

it('rechaza los endpoints de campo sin token', function () {
    pilotoDeCampo();

    $this->getJson('/api/auth/sesion')->assertUnauthorized();
    $this->getJson('/api/dispositivos')->assertUnauthorized();
    // El listado de órdenes también quedó detrás del token (TODO(HU-03) de
    // routes/api.php): la API de campo no expone datos operativos sin auth.
    $this->getJson('/api/ordenes')->assertUnauthorized();
});

it('rechaza un token inventado', function () {
    pilotoDeCampo();
    emitirToken();

    $this->withHeader('Authorization', 'Bearer 1|estonoesuntokenvalido')
        ->getJson('/api/auth/sesion')
        ->assertUnauthorized();
});

it('mantiene un único token vivo por dispositivo: reloguear rota la credencial', function () {
    pilotoDeCampo();

    $primerToken = emitirToken();
    $segundoToken = emitirToken();

    expect($primerToken)->not->toBe($segundoToken)
        ->and(SecTokenDispositivo::query()->count())->toBe(1)
        // La fila anterior no se borra físico: queda como historial (ADR 0007).
        ->and(SecTokenDispositivo::withTrashed()->count())->toBe(2);

    comoDispositivo($primerToken)
        ->getJson('/api/auth/sesion')
        ->assertUnauthorized();

    comoDispositivo($segundoToken)
        ->getJson('/api/auth/sesion')
        ->assertOk();
});

it('emite tokens independientes para dos dispositivos del mismo usuario', function () {
    pilotoDeCampo();

    $telefono = emitirToken();
    $tablet = emitirToken(['uuid_dispositivo' => '11111111-2222-4333-8444-555555555555']);

    expect(SecTokenDispositivo::query()->count())->toBe(2);

    comoDispositivo($telefono)->getJson('/api/auth/sesion')->assertOk();
    comoDispositivo($tablet)->getJson('/api/auth/sesion')->assertOk();
});

it('rechaza credenciales incorrectas sin emitir ningún token', function () {
    pilotoDeCampo();

    $this->postJson('/api/auth/token', [
        'username' => 'jperez',
        'password' => 'equivocada',
        'uuid_dispositivo' => UUID_EQUIPO,
    ])->assertStatus(422);

    expect(SecTokenDispositivo::withTrashed()->count())->toBe(0);
});

it('no emite token a una cuenta deshabilitada', function () {
    pilotoDeCampo(['state' => false]);

    $this->postJson('/api/auth/token', [
        'username' => 'jperez',
        'password' => 'Secreta123',
        'uuid_dispositivo' => UUID_EQUIPO,
    ])->assertStatus(422);

    expect(SecTokenDispositivo::withTrashed()->count())->toBe(0);
});

it('exige un uuid de dispositivo válido', function () {
    pilotoDeCampo();

    $this->postJson('/api/auth/token', [
        'username' => 'jperez',
        'password' => 'Secreta123',
        'uuid_dispositivo' => 'el-telefono-de-juan',
    ])->assertStatus(422)->assertJsonValidationErrors('uuid_dispositivo');
});

it('cierra la sesión del propio dispositivo y deja de valer en el acto', function () {
    pilotoDeCampo();
    $tokenPlano = emitirToken();

    comoDispositivo($tokenPlano)
        ->deleteJson('/api/auth/token')
        ->assertNoContent();

    comoDispositivo($tokenPlano)
        ->getJson('/api/auth/sesion')
        ->assertUnauthorized();
});

it('sella createToken(): la emisión pasa por el caso de uso, que conoce dispositivo y rol', function () {
    $usuario = pilotoDeCampo();

    expect(fn () => $usuario->createToken('ad-hoc'))
        ->toThrow(EmisionDirectaDeTokenNoPermitida::class);

    expect(SecTokenDispositivo::withTrashed()->count())->toBe(0);
});

it('no permite borrar físicamente un token (ADR 0007)', function () {
    pilotoDeCampo();
    emitirToken();

    expect(fn () => SecTokenDispositivo::query()->sole()->forceDelete())
        ->toThrow(BorradoFisicoNoPermitido::class);
});

/*
 * CA "sesión persistente offline": el token no caduca por tiempo. Un piloto
 * puede pasar semanas sin señal y su sesión tiene que seguir viva al volver.
 */
it('mantiene la sesión viva después de meses sin usarse', function () {
    pilotoDeCampo();
    $tokenPlano = emitirToken();

    $this->travel(120)->days();

    comoDispositivo($tokenPlano)
        ->getJson('/api/auth/sesion')
        ->assertOk()
        ->assertJsonPath('usuario.username', 'jperez');
})->group('offline');

it('no configura caducidad global de tokens', function () {
    // `sanctum.expiration` pisa el expires_at de cada fila: si dejara de ser
    // null, TODOS los tokens de campo caducarían de golpe y la sesión
    // persistente offline dejaría de existir sin que nadie toque este módulo.
    expect(config('sanctum.expiration'))->toBeNull();
})->group('offline');

it('no autentica la API de campo con la sesión del panel (ADR 0008)', function () {
    $usuario = pilotoDeCampo();

    // Con `sanctum.guard` en su default (`['web']`), este request quedaría
    // autenticado por la sesión del panel, sin token de dispositivo.
    expect(config('sanctum.guard'))->toBe([]);

    $this->actingAs($usuario, 'interno')
        ->getJson('/api/auth/sesion')
        ->assertUnauthorized();
});

it('deja de autenticar cuando le revocan al usuario el rol con el que se emitió el token', function () {
    $usuario = pilotoDeCampo();
    $tokenPlano = emitirToken();

    comoDispositivo($tokenPlano)->getJson('/api/auth/sesion')->assertOk();

    // Revocación de la asignación de rol (soft delete de sec_user_role): el
    // token sigue existiendo, pero ya no representa ninguna sesión válida —
    // mismo criterio que ResolverRolActivo en el panel, revalidado en cada
    // request.
    SecUserRole::query()->where('id_user', $usuario->id)->sole()->delete();

    comoDispositivo($tokenPlano)
        ->getJson('/api/auth/sesion')
        ->assertUnauthorized();
});

it('deja de autenticar cuando se deshabilita la cuenta', function () {
    $usuario = pilotoDeCampo();
    $tokenPlano = emitirToken();

    $usuario->state = false;
    $usuario->save();

    comoDispositivo($tokenPlano)
        ->getJson('/api/auth/sesion')
        ->assertUnauthorized();
});

it('pide elegir rol cuando el usuario tiene más de uno, sin emitir token', function () {
    $usuario = pilotoDeCampo();
    asignarRol($usuario, 'jefe_campo');

    $this->postJson('/api/auth/token', [
        'username' => 'jperez',
        'password' => 'Secreta123',
        'uuid_dispositivo' => UUID_EQUIPO,
    ])->assertStatus(409)
        ->assertJsonCount(2, 'roles')
        ->assertJsonStructure(['message', 'roles' => [['id', 'name', 'description']]]);

    expect(SecTokenDispositivo::withTrashed()->count())->toBe(0);
});

it('emite con el rol elegido cuando el usuario tiene varios', function () {
    $usuario = pilotoDeCampo();
    $idJefe = asignarRol($usuario, 'jefe_campo');

    $this->postJson('/api/auth/token', [
        'username' => 'jperez',
        'password' => 'Secreta123',
        'uuid_dispositivo' => UUID_EQUIPO,
        'role_id' => $idJefe,
    ])->assertCreated()->assertJsonPath('rol.id', $idJefe);

    expect(SecTokenDispositivo::query()->sole()->role_id)->toBe($idJefe);
});

it('rechaza un rol que el usuario no tiene asignado', function () {
    pilotoDeCampo();
    $idDueno = (int) SecRole::query()->where('name', 'dueno')->value('id');

    $this->postJson('/api/auth/token', [
        'username' => 'jperez',
        'password' => 'Secreta123',
        'uuid_dispositivo' => UUID_EQUIPO,
        'role_id' => $idDueno,
    ])->assertForbidden();

    expect(SecTokenDispositivo::withTrashed()->count())->toBe(0);
});

it('el caso de uso no emite nada cuando el usuario no tiene ningún rol vivo', function () {
    $usuario = SecUser::factory()->create();

    $resultado = app(EmitirTokenDispositivo::class)->ejecutar($usuario, UUID_EQUIPO);

    expect($resultado->requiereSeleccionDeRol)->toBeTrue()
        ->and($resultado->token)->toBeNull()
        ->and($resultado->rolesDisponibles)->toBeEmpty()
        ->and(SecTokenDispositivo::withTrashed()->count())->toBe(0);
});

it('registra el último uso sin reescribir quién emitió el token', function () {
    $usuario = pilotoDeCampo();
    $tokenPlano = emitirToken();

    expect(SecTokenDispositivo::query()->sole()->last_used_at)->toBeNull();

    comoDispositivo($tokenPlano)->getJson('/api/auth/sesion')->assertOk();

    $token = SecTokenDispositivo::query()->sole();

    expect($token->last_used_at)->not->toBeNull()
        // Usar la app no es una edición del token: `updated_by` sigue
        // diciendo quién lo emitió, no quién lo usó (por eso el listener de
        // último uso escribe con saveQuietly()).
        ->and($token->updated_by)->toBe($usuario->id);
});

it('no emite token a una cuenta de portal, ni la deja autenticar si alguien se lo emitiera', function () {
    // La app de campo es de personal interno. La defensa es doble y ninguna
    // de las dos estaba blindada: el global scope `type = 'interno'` de
    // SecUsuarioInterno, que impide el login, y el provider
    // `usuarios_internos` del guard sanctum (config/auth.php), que hace que
    // `Guard::hasValidProvider()` rechace al dueño del token aunque el token
    // exista y esté vivo.
    $cliente = SecUser::factory()->create([
        'username' => 'cliente.portal',
        'password' => 'Secreta123',
        'type' => TipoUsuario::Cliente,
    ]);
    asignarRol($cliente, 'piloto');

    // 422 y no 401: para el guard `interno` esa cuenta directamente no
    // existe, así que el login la trata igual que a un username inventado —
    // sin revelar que existe como cuenta de portal.
    test()->postJson('/api/auth/token', [
        'username' => 'cliente.portal',
        'password' => 'Secreta123',
        'uuid_dispositivo' => UUID_EQUIPO,
    ])->assertUnprocessable();

    expect(SecTokenDispositivo::withTrashed()->count())->toBe(0);

    // Y por si el token naciera por otra vía que la del login:
    $plano = Str::random(40);
    $token = new SecTokenDispositivo([
        'user_id' => $cliente->id,
        'role_id' => SecRole::query()->where('name', 'piloto')->value('id'),
        'uuid_dispositivo' => UUID_EQUIPO,
        'nombre_dispositivo' => 'equipo de un cliente',
        'token' => hash('sha256', $plano),
        'abilities' => ['*'],
    ]);
    $token->created_by = $cliente->id;
    $token->updated_by = $cliente->id;
    $token->save();

    comoDispositivo("{$token->id}|{$plano}")
        ->getJson('/api/auth/sesion')
        ->assertUnauthorized();
});
