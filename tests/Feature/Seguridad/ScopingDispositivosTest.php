<?php

use App\Dominios\Seguridad\Aplicacion\BuscarDispositivoDeUsuario;
use App\Dominios\Seguridad\Dominio\Excepciones\DispositivoNoEncontrado;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-03 — scoping de la API de campo: un token de un dispositivo NO sirve
 * para leer recursos de otro usuario.
 *
 * Es la misma regla que la invariante 5 de CLAUDE.md fija para el portal del
 * cliente, aplicada acá: se consulta desde la relación del usuario del token,
 * nunca desde la tabla global con un `where` agregado al final — y un recurso
 * ajeno devuelve 404, no 403, para no confirmar que ese id existe.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
});

/** @return array{0: SecUser, 1: string, 2: SecTokenDispositivo} usuario, token en claro, fila del token */
function operarioConDispositivo(string $username, string $uuidDispositivo): array
{
    $usuario = SecUser::factory()->create([
        'username' => $username,
        'password' => 'Secreta123',
    ]);

    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    $respuesta = test()->postJson('/api/auth/token', [
        'username' => $username,
        'password' => 'Secreta123',
        'uuid_dispositivo' => $uuidDispositivo,
        'nombre_dispositivo' => "Equipo de {$username}",
    ]);

    $respuesta->assertCreated();

    $token = SecTokenDispositivo::query()->where('user_id', $usuario->id)->sole();

    return [$usuario, (string) $respuesta->json('token'), $token];
}

it('no deja leer el dispositivo de otro usuario: 404, no 403', function () {
    [, $tokenDeA] = operarioConDispositivo('piloto.a', '11111111-1111-4111-8111-111111111111');
    [, , $dispositivoDeB] = operarioConDispositivo('piloto.b', '22222222-2222-4222-8222-222222222222');

    // A existe, B existe, y el id de B es real: lo único que lo protege es el
    // scoping por el usuario del token.
    comoDispositivo($tokenDeA)
        ->getJson("/api/dispositivos/{$dispositivoDeB->id}")
        ->assertNotFound();
});

it('el listado de dispositivos devuelve solo los del dueño del token', function () {
    [, $tokenDeA, $dispositivoDeA] = operarioConDispositivo('piloto.a', '11111111-1111-4111-8111-111111111111');
    [, , $dispositivoDeB] = operarioConDispositivo('piloto.b', '22222222-2222-4222-8222-222222222222');

    $respuesta = comoDispositivo($tokenDeA)
        ->getJson('/api/dispositivos')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $dispositivoDeA->id);

    expect(collect($respuesta->json('data'))->pluck('id'))->not->toContain($dispositivoDeB->id);
});

it('sí deja leer el dispositivo propio', function () {
    [, $tokenDeA, $dispositivoDeA] = operarioConDispositivo('piloto.a', '11111111-1111-4111-8111-111111111111');

    comoDispositivo($tokenDeA)
        ->getJson("/api/dispositivos/{$dispositivoDeA->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $dispositivoDeA->id);
});

it('tampoco deja cerrar la sesión de otro: el logout solo alcanza al token que firma el request', function () {
    [, $tokenDeA] = operarioConDispositivo('piloto.a', '11111111-1111-4111-8111-111111111111');
    [, $tokenDeB, $dispositivoDeB] = operarioConDispositivo('piloto.b', '22222222-2222-4222-8222-222222222222');

    comoDispositivo($tokenDeA)
        ->deleteJson('/api/auth/token')
        ->assertNoContent();

    // La sesión de B sigue viva: no hay forma de que A la toque desde la app.
    expect($dispositivoDeB->fresh()?->trashed())->toBeFalse();

    comoDispositivo($tokenDeB)
        ->getJson('/api/auth/sesion')
        ->assertOk();
});

it('un id inexistente devuelve 404 igual que uno ajeno, sin distinguirlos', function () {
    [, $tokenDeA] = operarioConDispositivo('piloto.a', '11111111-1111-4111-8111-111111111111');

    comoDispositivo($tokenDeA)
        ->getJson('/api/dispositivos/999999')
        ->assertNotFound();
});

it('el caso de uso acota por el usuario, no por un where agregado después', function () {
    [$usuarioA] = operarioConDispositivo('piloto.a', '11111111-1111-4111-8111-111111111111');
    [, , $dispositivoDeB] = operarioConDispositivo('piloto.b', '22222222-2222-4222-8222-222222222222');

    expect(fn () => app(BuscarDispositivoDeUsuario::class)->ejecutar($usuarioA, $dispositivoDeB->id))
        ->toThrow(DispositivoNoEncontrado::class);
});

it('las órdenes de la app de campo exigen un token vigente', function () {
    [, $tokenDeA, $dispositivoDeA] = operarioConDispositivo('piloto.a', '11111111-1111-4111-8111-111111111111');

    comoDispositivo($tokenDeA)
        ->getJson('/api/ordenes')
        ->assertOk();

    $dispositivoDeA->delete();

    comoDispositivo($tokenDeA)
        ->getJson('/api/ordenes')
        ->assertUnauthorized();
});
