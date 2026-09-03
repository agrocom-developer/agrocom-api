<?php

use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-45 (tarea 39): gestión de usuarios desde el panel — la capa HTTP sobre
 * `AsignarRolesUsuario` (HU-01, ya testeado en AsignarRolesUsuarioTest.php).
 * Permisos evaluados contra el ROL ACTIVO de la sesión, nunca la unión de
 * los roles del usuario (invariante 10 de CLAUDE.md). Mismo patrón de
 * asserts que tests/Feature/Personal/GestionPersonasPanelTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaUsuarios(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

/** Entra al panel con un rol activo fijado, como haría el login. */
function entrarAlPanelParaUsuarios(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de alta/edición. */
function payloadUsuario(array $overrides = []): array
{
    return array_merge([
        'name' => 'Nuevo Usuario',
        'username' => 'nuevo.usuario',
        'password' => 'Secreta123',
        'persona_id' => '',
        'roles' => [],
    ], $overrides);
}

it('da de alta un usuario con roles asignados y el login real funciona con esos datos', function () {
    [$encargado, $idRol] = usuarioConRolParaUsuarios('encargado', 'encargado_operaciones');
    entrarAlPanelParaUsuarios($encargado, $idRol);

    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');

    $this->post(route('panel.usuarios.store'), payloadUsuario(['roles' => [$idPiloto]]))
        ->assertRedirect(route('panel.usuarios.index'));

    $usuario = SecUser::query()->where('username', 'nuevo.usuario')->sole();
    expect($usuario->name)->toBe('Nuevo Usuario')
        ->and($usuario->idsDeRoles())->toBe([$idPiloto]);

    $this->post('/logout');

    $this->postJson('/login', ['username' => 'nuevo.usuario', 'password' => 'Secreta123'])
        ->assertOk()
        ->assertJson(['requiere_seleccion_rol' => false, 'rol_activo_id' => $idPiloto]);
});

it('la edición reasigna roles (agrega uno, revoca otro) y actualiza name y persona_id', function () {
    [$duenio, $idRolDuenio] = usuarioConRolParaUsuarios('duenio', 'dueno');
    entrarAlPanelParaUsuarios($duenio, $idRolDuenio);

    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $idAuxiliar = (int) SecRole::query()->where('name', 'auxiliar')->value('id');

    $this->post(route('panel.usuarios.store'), payloadUsuario(['roles' => [$idPiloto]]));
    $usuario = SecUser::query()->where('username', 'nuevo.usuario')->sole();

    $persona = PerPersona::query()->create([
        'nombre' => 'Persona Asociable',
        'rol' => RolOperativoPersona::Piloto,
        'activo' => true,
    ]);

    $this->put(route('panel.usuarios.update', $usuario), payloadUsuario([
        'name' => 'Usuario Editado',
        'persona_id' => (string) $persona->id,
        'roles' => [$idAuxiliar],
    ]))->assertRedirect(route('panel.usuarios.index'));

    $usuario->refresh();
    expect($usuario->name)->toBe('Usuario Editado')
        ->and($usuario->persona_id)->toBe($persona->id)
        ->and($usuario->idsDeRoles())->toBe([$idAuxiliar]);
});

it('un encargado sin asignar_rol_dueno no puede crear un usuario con rol dueño: error legible, nunca 500', function () {
    [$encargado, $idRol] = usuarioConRolParaUsuarios('encargado', 'encargado_operaciones');
    entrarAlPanelParaUsuarios($encargado, $idRol);

    $idDueno = (int) SecRole::query()->where('name', 'dueno')->value('id');

    $this->post(route('panel.usuarios.store'), payloadUsuario(['roles' => [$idDueno]]))
        ->assertRedirect()
        ->assertSessionHasErrors('estado');

    expect(SecUser::query()->where('username', 'nuevo.usuario')->exists())->toBeFalse();
});

it('un encargado sin asignar_rol_dueno tampoco puede lograrlo editando después: error legible, nunca 500', function () {
    [$encargado, $idRol] = usuarioConRolParaUsuarios('encargado', 'encargado_operaciones');
    entrarAlPanelParaUsuarios($encargado, $idRol);

    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $idDueno = (int) SecRole::query()->where('name', 'dueno')->value('id');

    $this->post(route('panel.usuarios.store'), payloadUsuario(['roles' => [$idPiloto]]));
    $usuario = SecUser::query()->where('username', 'nuevo.usuario')->sole();

    $this->put(route('panel.usuarios.update', $usuario), payloadUsuario(['roles' => [$idDueno]]))
        ->assertRedirect()
        ->assertSessionHasErrors('estado');

    expect($usuario->fresh()?->idsDeRoles())->not->toContain($idDueno);
});

it('da de baja un usuario por soft delete: no aparece en el índice y un segundo intento da 404', function () {
    [$encargado, $idRol] = usuarioConRolParaUsuarios('encargado', 'encargado_operaciones');
    entrarAlPanelParaUsuarios($encargado, $idRol);

    $this->post(route('panel.usuarios.store'), payloadUsuario());
    $usuario = SecUser::query()->where('username', 'nuevo.usuario')->sole();

    $this->delete(route('panel.usuarios.destroy', $usuario))
        ->assertRedirect(route('panel.usuarios.index'));

    $borrado = SecUser::withTrashed()->findOrFail($usuario->id);
    expect($borrado->trashed())->toBeTrue();

    $this->get(route('panel.usuarios.index'))
        ->assertOk()
        ->assertDontSee('nuevo.usuario');

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.usuarios.destroy', $usuario))->assertNotFound();
});

it('rechaza un username duplicado entre cuentas vivas con un error de validación legible', function () {
    [$encargado, $idRol] = usuarioConRolParaUsuarios('encargado', 'encargado_operaciones');
    entrarAlPanelParaUsuarios($encargado, $idRol);

    $this->post(route('panel.usuarios.store'), payloadUsuario(['username' => 'repetido']))
        ->assertRedirect(route('panel.usuarios.index'));

    $this->post(route('panel.usuarios.store'), payloadUsuario(['username' => 'repetido', 'name' => 'Segundo']))
        ->assertSessionHasErrors('username');

    expect(SecUser::query()->where('username', 'repetido')->count())->toBe(1);
});

it('el toggle de bloqueo no borra al usuario, y el login rechaza la cuenta bloqueada', function () {
    [$encargado, $idRol] = usuarioConRolParaUsuarios('encargado', 'encargado_operaciones');
    entrarAlPanelParaUsuarios($encargado, $idRol);

    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $this->post(route('panel.usuarios.store'), payloadUsuario(['roles' => [$idPiloto]]));
    $usuario = SecUser::query()->where('username', 'nuevo.usuario')->sole();
    expect($usuario->state)->toBeTrue();

    $this->post(route('panel.usuarios.bloqueo', $usuario))
        ->assertRedirect(route('panel.usuarios.index'));

    $usuario->refresh();
    expect($usuario->state)->toBeFalse()
        ->and($usuario->trashed())->toBeFalse()
        ->and($usuario->deleted_at)->toBeNull();

    $this->get(route('panel.usuarios.index'))
        ->assertOk()
        ->assertSee('nuevo.usuario');

    // El login ya filtra por `state = true` (IniciarSesionPanel) — esto
    // CONFIRMA ese comportamiento existente, no agrega ningún mecanismo.
    $this->post('/logout');
    $this->postJson('/login', ['username' => 'nuevo.usuario', 'password' => 'Secreta123'])
        ->assertStatus(422);

    // Desbloquear revierte el toggle y el login vuelve a andar.
    entrarAlPanelParaUsuarios($encargado, $idRol);
    $this->post(route('panel.usuarios.bloqueo', $usuario))
        ->assertRedirect(route('panel.usuarios.index'));
    expect($usuario->fresh()?->state)->toBeTrue();

    $this->post('/logout');
    $this->postJson('/login', ['username' => 'nuevo.usuario', 'password' => 'Secreta123'])
        ->assertOk()
        ->assertJson(['requiere_seleccion_rol' => false, 'rol_activo_id' => $idPiloto]);
});

it('reasignar los roles de un usuario logueado no invalida su sesión activa: el próximo request se resuelve solo', function () {
    [$encargado, $idRolEncargado] = usuarioConRolParaUsuarios('encargado', 'encargado_operaciones');

    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $idJefeCampo = (int) SecRole::query()->where('name', 'jefe_campo')->value('id');

    [$usuarioA] = usuarioConRolParaUsuarios('usuario.a', 'piloto');

    entrarAlPanelParaUsuarios($usuarioA, $idPiloto);
    $this->get(route('panel.dashboard'))->assertOk();

    // El encargado reasigna el conjunto completo de roles de A: pierde
    // "piloto" (su rol activo de sesión) y gana "jefe_campo" — mismo caso de
    // uso que el resto de esta HU, `AsignarRolesUsuario`.
    entrarAlPanelParaUsuarios($encargado, $idRolEncargado);
    $this->put(route('panel.usuarios.update', $usuarioA), payloadUsuario([
        'name' => $usuarioA->name,
        'username' => $usuarioA->username,
        'password' => '',
        'roles' => [$idJefeCampo],
    ]))->assertRedirect(route('panel.usuarios.index'));

    // La "cookie" de A sigue apuntando al id de rol que tenía activo — igual
    // que en un navegador real, nadie le avisó del cambio. ResolverRolActivo
    // revalida contra la base en cada request: con un único rol vivo
    // remanente (jefe_campo), lo autofija solo — nunca un 500 por el
    // `id_role` fantasma.
    entrarAlPanelParaUsuarios($usuarioA, $idPiloto);
    $respuesta = $this->get(route('panel.dashboard'));

    expect($respuesta->status())->not->toBe(500);
    $respuesta->assertOk();
    expect(session('sec_rol_activo_id'))->toBe($idJefeCampo);
});

it('un rol sin los permisos correspondientes recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaUsuarios('piloto.curioso', 'piloto');
    entrarAlPanelParaUsuarios($piloto, $idRol);

    [$otro] = usuarioConRolParaUsuarios('otro.usuario', 'auxiliar');

    $this->get(route('panel.usuarios.index'))->assertForbidden();
    $this->get(route('panel.usuarios.create'))->assertForbidden();
    $this->post(route('panel.usuarios.store'), payloadUsuario())->assertForbidden();
    $this->get(route('panel.usuarios.edit', $otro))->assertForbidden();
    $this->put(route('panel.usuarios.update', $otro), payloadUsuario())->assertForbidden();
    $this->post(route('panel.usuarios.bloqueo', $otro))->assertForbidden();
    $this->delete(route('panel.usuarios.destroy', $otro))->assertForbidden();

    expect(SecUser::query()->where('username', 'nuevo.usuario')->exists())->toBeFalse()
        ->and($otro->fresh()?->trashed())->toBeFalse()
        ->and($otro->fresh()?->state)->toBeTrue();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    [$multirol, $idEncargado] = usuarioConRolParaUsuarios('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaUsuarios($multirol, $idPiloto);
    $this->post(route('panel.usuarios.store'), payloadUsuario())->assertForbidden();
    expect(SecUser::query()->where('username', 'nuevo.usuario')->exists())->toBeFalse();

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaUsuarios($multirol, $idEncargado);
    $this->post(route('panel.usuarios.store'), payloadUsuario())->assertRedirect();
    expect(SecUser::query()->where('username', 'nuevo.usuario')->exists())->toBeTrue();
});
