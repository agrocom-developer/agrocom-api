<?php

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-03 — CA "revocable desde el panel": la pantalla desde la que se corta el
 * acceso de un dispositivo de campo (teléfono perdido, alguien que deja la
 * empresa), y los permisos que la gobiernan.
 *
 * Permisos evaluados contra el ROL ACTIVO de la sesión, nunca la unión de los
 * roles del usuario (invariante 10 de CLAUDE.md).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRol(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

/** Deja a un piloto con sesión abierta en su teléfono y devuelve el token. */
function dispositivoDeCampo(string $username = 'piloto.campo'): array
{
    [$piloto] = usuarioConRol($username, 'piloto');

    $respuesta = test()->postJson('/api/auth/token', [
        'username' => $username,
        'password' => 'Secreta123',
        'uuid_dispositivo' => '6f1d0a2e-1f34-4c9f-9a8b-2b7c1d5e0f31',
        'nombre_dispositivo' => 'Moto G84 — piloto 2',
    ]);

    $respuesta->assertCreated();

    return [$piloto, (string) $respuesta->json('token'), SecTokenDispositivo::query()->sole()];
}

/** Entra al panel con un rol activo fijado, como haría el login. */
function entrarAlPanel(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

it('el encargado de operaciones ve los dispositivos con sesión abierta', function () {
    [, , $dispositivo] = dispositivoDeCampo();
    [$encargado, $idRol] = usuarioConRol('encargado', 'encargado_operaciones');

    entrarAlPanel($encargado, $idRol);

    $this->get('/panel/dispositivos')
        ->assertOk()
        ->assertSee('Moto G84 — piloto 2', escape: false)
        ->assertSee($dispositivo->uuid_dispositivo);
});

it('revoca el acceso de un dispositivo desde el panel y el token deja de valer', function () {
    [, $tokenPlano, $dispositivo] = dispositivoDeCampo();
    [$encargado, $idRol] = usuarioConRol('encargado', 'encargado_operaciones');

    entrarAlPanel($encargado, $idRol);

    $this->delete("/panel/dispositivos/{$dispositivo->id}")
        ->assertRedirect(route('panel.dispositivos.index'));

    // Borrado lógico, nunca físico: la fila queda para auditoría con quién
    // revocó (ADR 0007).
    $revocado = SecTokenDispositivo::withTrashed()->findOrFail($dispositivo->id);

    expect($revocado->trashed())->toBeTrue()
        ->and($revocado->updated_by)->toBe($encargado->id);

    // La revocación tiene efecto en el request siguiente, sin esperar a
    // ninguna caducidad.
    comoDispositivo($tokenPlano)
        ->getJson('/api/auth/sesion')
        ->assertUnauthorized();
});

it('el piloto no puede ver ni revocar dispositivos: no tiene esos permisos', function () {
    [, , $dispositivo] = dispositivoDeCampo();
    [$otroPiloto, $idRol] = usuarioConRol('piloto.curioso', 'piloto');

    entrarAlPanel($otroPiloto, $idRol);

    $this->get('/panel/dispositivos')->assertForbidden();
    $this->delete("/panel/dispositivos/{$dispositivo->id}")->assertForbidden();

    expect($dispositivo->fresh()?->trashed())->toBeFalse();
});

it('no deja revocar a quien tiene el permiso en otro rol pero no en el activo', function () {
    [, , $dispositivo] = dispositivoDeCampo();

    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede revocar — los permisos efectivos son los del
    // rol activo, jamás la unión.
    [$multirol, $idEncargado] = usuarioConRol('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanel($multirol, $idPiloto);
    $this->delete("/panel/dispositivos/{$dispositivo->id}")->assertForbidden();
    expect($dispositivo->fresh()?->trashed())->toBeFalse();

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanel($multirol, $idEncargado);
    $this->delete("/panel/dispositivos/{$dispositivo->id}")->assertRedirect();
    expect($dispositivo->fresh()?->trashed())->toBeTrue();
});

it('exige sesión de panel para llegar a la pantalla', function () {
    $this->get('/panel/dispositivos')->assertRedirect();
});

it('un dispositivo ya revocado no vuelve a revocarse: deja de existir para el panel', function () {
    [, , $dispositivo] = dispositivoDeCampo();
    [$encargado, $idRol] = usuarioConRol('encargado', 'encargado_operaciones');
    [$dueno, $idDueno] = usuarioConRol('duenio', 'dueno');

    entrarAlPanel($encargado, $idRol);
    $this->delete("/panel/dispositivos/{$dispositivo->id}")->assertRedirect();

    // El route model binding no resuelve filas borradas lógicamente, así que
    // el segundo intento es 404 — y quién revocó primero queda intacto en la
    // bitácora, que es lo que importa para la auditoría (ADR 0007).
    entrarAlPanel($dueno, $idDueno);
    $this->delete("/panel/dispositivos/{$dispositivo->id}")->assertNotFound();

    expect(SecTokenDispositivo::withTrashed()->findOrFail($dispositivo->id)->updated_by)
        ->toBe($encargado->id);
});

it('publica el ítem de menú de dispositivos gateado por el permiso de listado', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.seguridad.items.dispositivos')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'seguridad.dispositivo.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.dispositivos.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
