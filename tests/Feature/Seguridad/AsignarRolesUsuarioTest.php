<?php

use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Aplicacion\AsignarRolesUsuario;
use App\Dominios\Seguridad\Dominio\Excepciones\PermisoDenegado;
use App\Dominios\Seguridad\Dominio\Excepciones\UsuarioDuplicado;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Database\Seeders\Catalogo\SeguridadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-01 — caso de uso AsignarRolesUsuario: alta y edición de usuarios
 * internos con multi-rol, protegidas por la misma guarda de permisos
 * (diseño `modulos-roles` §3). Sin controlador: se invoca directo, como
 * indica CLAUDE.md para las piezas que no se delegan sin revisión.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SeguridadSeeder::class);
    $this->caso = new AsignarRolesUsuario;
});

/**
 * Fixture: un SecUser persistido con los roles (por nombre) ya asignados,
 * armado directo por Eloquent — no pasa por el caso de uso bajo prueba, para
 * no acoplar el arranque del test a lo que se está probando.
 *
 * @param  list<string>  $nombresDeRol
 */
function segCrearActorConRoles(array $nombresDeRol): SecUser
{
    $actor = SecUser::factory()->create();

    foreach ($nombresDeRol as $nombre) {
        $idRol = SecRole::query()->where('name', $nombre)->value('id');

        $pivote = new SecUserRole(['id_user' => $actor->id, 'id_role' => $idRol]);
        $pivote->created_by = $actor->id;
        $pivote->updated_by = $actor->id;
        $pivote->save();
    }

    return $actor->refresh();
}

function segIdDeRol(string $nombre): int
{
    return (int) SecRole::query()->where('name', $nombre)->value('id');
}

it('crea un usuario con múltiples roles', function () {
    $duenio = segCrearActorConRoles(['dueno']);

    $usuario = $this->caso->ejecutar(
        actor: $duenio,
        usuarioId: null,
        username: 'jperez',
        password: 'Secreta123',
        name: 'Juan Pérez',
        type: TipoUsuario::Interno,
        personaId: null,
        contratoId: null,
        roleIds: [segIdDeRol('piloto'), segIdDeRol('auxiliar')],
    );

    expect($usuario->exists)->toBeTrue()
        ->and($usuario->username)->toBe('jperez')
        ->and($usuario->password)->not->toBe('Secreta123') // quedó hasheado
        ->and(SecUserRole::query()->where('id_user', $usuario->id)->count())->toBe(2);
});

it('un encargado de operaciones sin el permiso de asignar_rol_dueno no puede crear un usuario dueño', function () {
    $encargado = segCrearActorConRoles(['encargado_operaciones']);

    expect(fn () => $this->caso->ejecutar(
        actor: $encargado,
        usuarioId: null,
        username: 'nuevo.dueno',
        password: 'Secreta123',
        name: 'Aspirante a Dueño',
        type: TipoUsuario::Interno,
        personaId: null,
        contratoId: null,
        roleIds: [segIdDeRol('dueno')],
    ))->toThrow(PermisoDenegado::class);

    expect(SecUser::query()->where('username', 'nuevo.dueno')->exists())->toBeFalse();
});

it('un encargado sin el permiso tampoco puede lograrlo asignando el rol dueño al editar después', function () {
    $encargado = segCrearActorConRoles(['encargado_operaciones']);

    $usuario = $this->caso->ejecutar(
        actor: $encargado,
        usuarioId: null,
        username: 'futuro.piloto',
        password: 'Secreta123',
        name: 'Futuro Piloto',
        type: TipoUsuario::Interno,
        personaId: null,
        contratoId: null,
        roleIds: [segIdDeRol('piloto')],
    );

    expect(fn () => $this->caso->ejecutar(
        actor: $encargado,
        usuarioId: $usuario->id,
        username: $usuario->username,
        password: null,
        name: $usuario->name,
        type: TipoUsuario::Interno,
        personaId: null,
        contratoId: null,
        roleIds: [segIdDeRol('piloto'), segIdDeRol('dueno')],
    ))->toThrow(PermisoDenegado::class);

    // La edición fallida no debe haber dejado el rol dueño asignado.
    expect(SecUserRole::query()->where('id_user', $usuario->id)->where('id_role', segIdDeRol('dueno'))->exists())
        ->toBeFalse();
});

it('un actor con el permiso asignar_rol_dueno sí puede crear un usuario dueño', function () {
    $duenio = segCrearActorConRoles(['dueno']);

    $usuario = $this->caso->ejecutar(
        actor: $duenio,
        usuarioId: null,
        username: 'segundo.dueno',
        password: 'Secreta123',
        name: 'Segundo Dueño',
        type: TipoUsuario::Interno,
        personaId: null,
        contratoId: null,
        roleIds: [segIdDeRol('dueno')],
    );

    expect(
        SecUserRole::query()->where('id_user', $usuario->id)->where('id_role', segIdDeRol('dueno'))->exists(),
    )->toBeTrue();
});

it('un actor con el permiso asignar_rol_dueno también puede quitar el rol dueño al editar', function () {
    $duenio = segCrearActorConRoles(['dueno']);

    $usuario = $this->caso->ejecutar(
        actor: $duenio,
        usuarioId: null,
        username: 'ex.dueno',
        password: 'Secreta123',
        name: 'Ex Dueño',
        type: TipoUsuario::Interno,
        personaId: null,
        contratoId: null,
        roleIds: [segIdDeRol('dueno')],
    );

    $this->caso->ejecutar(
        actor: $duenio,
        usuarioId: $usuario->id,
        username: $usuario->username,
        password: null,
        name: $usuario->name,
        type: TipoUsuario::Interno,
        personaId: null,
        contratoId: null,
        roleIds: [segIdDeRol('piloto')],
    );

    expect(
        SecUserRole::query()->where('id_user', $usuario->id)->where('id_role', segIdDeRol('dueno'))->exists(),
    )->toBeFalse();
});

it('rechaza dos altas con la misma persona_id', function () {
    $duenio = segCrearActorConRoles(['dueno']);
    $persona = PerPersona::query()->create([
        'nombre' => 'Piloto Base',
        'rol' => RolOperativoPersona::Piloto,
        'base_id' => null,
        'activo' => true,
    ]);

    $this->caso->ejecutar(
        actor: $duenio,
        usuarioId: null,
        username: 'piloto.uno',
        password: 'Secreta123',
        name: 'Piloto Uno',
        type: TipoUsuario::Interno,
        personaId: $persona->id,
        contratoId: null,
        roleIds: [segIdDeRol('piloto')],
    );

    expect(fn () => $this->caso->ejecutar(
        actor: $duenio,
        usuarioId: null,
        username: 'piloto.dos',
        password: 'Secreta123',
        name: 'Piloto Dos',
        type: TipoUsuario::Interno,
        personaId: $persona->id,
        contratoId: null,
        roleIds: [segIdDeRol('piloto')],
    ))->toThrow(UsuarioDuplicado::class);
});

it('rechaza el mismo username en dos altas vivas, pero lo libera tras la baja de la cuenta anterior', function () {
    $duenio = segCrearActorConRoles(['dueno']);

    $primero = $this->caso->ejecutar(
        actor: $duenio,
        usuarioId: null,
        username: 'repetido',
        password: 'Secreta123',
        name: 'Primero',
        type: TipoUsuario::Interno,
        personaId: null,
        contratoId: null,
        roleIds: [segIdDeRol('piloto')],
    );

    expect(fn () => $this->caso->ejecutar(
        actor: $duenio,
        usuarioId: null,
        username: 'repetido',
        password: 'Secreta123',
        name: 'Segundo',
        type: TipoUsuario::Interno,
        personaId: null,
        contratoId: null,
        roleIds: [segIdDeRol('piloto')],
    ))->toThrow(UsuarioDuplicado::class);

    $primero->delete();

    $segundo = $this->caso->ejecutar(
        actor: $duenio,
        usuarioId: null,
        username: 'repetido',
        password: 'Secreta123',
        name: 'Segundo',
        type: TipoUsuario::Interno,
        personaId: null,
        contratoId: null,
        roleIds: [segIdDeRol('piloto')],
    );

    expect($segundo->exists)->toBeTrue()
        ->and($segundo->id)->not->toBe($primero->id)
        ->and($segundo->username)->toBe('repetido');
});

it('revocar un rol al editar es soft delete de sec_user_role, no DELETE físico', function () {
    $duenio = segCrearActorConRoles(['dueno']);

    $usuario = $this->caso->ejecutar(
        actor: $duenio,
        usuarioId: null,
        username: 'con.dos.roles',
        password: 'Secreta123',
        name: 'Con Dos Roles',
        type: TipoUsuario::Interno,
        personaId: null,
        contratoId: null,
        roleIds: [segIdDeRol('piloto'), segIdDeRol('auxiliar')],
    );

    $pivotePiloto = SecUserRole::query()
        ->where('id_user', $usuario->id)
        ->where('id_role', segIdDeRol('piloto'))
        ->firstOrFail();

    $this->caso->ejecutar(
        actor: $duenio,
        usuarioId: $usuario->id,
        username: $usuario->username,
        password: null,
        name: $usuario->name,
        type: TipoUsuario::Interno,
        personaId: null,
        contratoId: null,
        roleIds: [segIdDeRol('auxiliar')],
    );

    expect(SecUserRole::query()->whereKey($pivotePiloto->id)->exists())->toBeFalse()
        ->and(SecUserRole::withTrashed()->whereKey($pivotePiloto->id)->exists())->toBeTrue()
        ->and(SecUserRole::withTrashed()->find($pivotePiloto->id)->deleted_at)->not->toBeNull()
        ->and(SecUserRole::withTrashed()->find($pivotePiloto->id)->updated_by)->toBe($duenio->id);
});
