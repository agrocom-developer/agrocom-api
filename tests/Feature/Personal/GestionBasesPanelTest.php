<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-26 (tarea 37): administración de bases operativas. Permisos evaluados
 * contra el ROL ACTIVO de la sesión, nunca la unión de los roles del
 * usuario (invariante 10 de CLAUDE.md). Mismo patrón de asserts que
 * tests/Feature/Operaciones/GestionDronesPanelTest.php (tarea 36).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaBases(string $username, string $rol): array
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
function entrarAlPanelParaBases(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de alta/edición. */
function payloadBase(array $overrides = []): array
{
    return array_merge([
        'nombre' => 'Base Norte',
        'ubicacion' => 'Km 12, ruta a Montero',
    ], $overrides);
}

it('da de alta una base con nombre y ubicación', function () {
    [$encargado, $idRol] = usuarioConRolParaBases('encargado', 'encargado_operaciones');
    entrarAlPanelParaBases($encargado, $idRol);

    $this->post(route('panel.bases.store'), payloadBase())
        ->assertRedirect(route('panel.bases.index'));

    $base = PerBase::query()->where('nombre', 'Base Norte')->sole();

    expect($base->ubicacion)->toBe('Km 12, ruta a Montero');
});

it('da de alta una base con coordenada (HU-85)', function () {
    [$encargado, $idRol] = usuarioConRolParaBases('encargado', 'encargado_operaciones');
    entrarAlPanelParaBases($encargado, $idRol);

    $this->post(route('panel.bases.store'), payloadBase([
        'latitud' => '-17.123456',
        'longitud' => '-63.123456',
    ]))->assertRedirect(route('panel.bases.index'));

    $base = PerBase::query()->where('nombre', 'Base Norte')->sole();

    expect($base->latitud)->toBe('-17.123456')
        ->and($base->longitud)->toBe('-63.123456');
});

it('edita la coordenada de una base', function () {
    [$encargado, $idRol] = usuarioConRolParaBases('encargado', 'encargado_operaciones');
    entrarAlPanelParaBases($encargado, $idRol);

    $this->post(route('panel.bases.store'), payloadBase());
    $base = PerBase::query()->sole();

    $this->put(route('panel.bases.update', $base), payloadBase([
        'latitud' => '-18.5',
        'longitud' => '-59.75',
    ]))->assertRedirect(route('panel.bases.index'));

    $base->refresh();
    expect($base->latitud)->toBe('-18.500000')
        ->and($base->longitud)->toBe('-59.750000');
});

it('rechaza una latitud fuera de rango', function () {
    [$encargado, $idRol] = usuarioConRolParaBases('encargado', 'encargado_operaciones');
    entrarAlPanelParaBases($encargado, $idRol);

    $this->post(route('panel.bases.store'), payloadBase([
        'latitud' => '-95',
        'longitud' => '-63',
    ]))->assertSessionHasErrors('latitud');

    expect(PerBase::query()->count())->toBe(0);
});

it('rechaza una longitud fuera de rango', function () {
    [$encargado, $idRol] = usuarioConRolParaBases('encargado', 'encargado_operaciones');
    entrarAlPanelParaBases($encargado, $idRol);

    $this->post(route('panel.bases.store'), payloadBase([
        'latitud' => '-17',
        'longitud' => '-185',
    ]))->assertSessionHasErrors('longitud');

    expect(PerBase::query()->count())->toBe(0);
});

it('rechaza latitud sin longitud', function () {
    [$encargado, $idRol] = usuarioConRolParaBases('encargado', 'encargado_operaciones');
    entrarAlPanelParaBases($encargado, $idRol);

    $this->post(route('panel.bases.store'), payloadBase([
        'latitud' => '-17.123456',
    ]))->assertSessionHasErrors('longitud');

    expect(PerBase::query()->count())->toBe(0);
});

it('rechaza longitud sin latitud', function () {
    [$encargado, $idRol] = usuarioConRolParaBases('encargado', 'encargado_operaciones');
    entrarAlPanelParaBases($encargado, $idRol);

    $this->post(route('panel.bases.store'), payloadBase([
        'longitud' => '-63.123456',
    ]))->assertSessionHasErrors('latitud');

    expect(PerBase::query()->count())->toBe(0);
});

it('acepta una base sin coordenada, en alta y en edición (regresión HU-85)', function () {
    [$encargado, $idRol] = usuarioConRolParaBases('encargado', 'encargado_operaciones');
    entrarAlPanelParaBases($encargado, $idRol);

    $this->post(route('panel.bases.store'), payloadBase())
        ->assertRedirect(route('panel.bases.index'));

    $base = PerBase::query()->sole();
    expect($base->latitud)->toBeNull()
        ->and($base->longitud)->toBeNull();

    $this->put(route('panel.bases.update', $base), payloadBase(['nombre' => 'Base Norte B']))
        ->assertRedirect(route('panel.bases.index'));

    $base->refresh();
    expect($base->latitud)->toBeNull()
        ->and($base->longitud)->toBeNull();
});

it('registra en bitácora el alta, la edición y la baja de una base', function () {
    [$encargado, $idRol] = usuarioConRolParaBases('encargado', 'encargado_operaciones');
    entrarAlPanelParaBases($encargado, $idRol);

    $this->post(route('panel.bases.store'), payloadBase());

    $base = PerBase::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'per_bases')
        ->where('registro_id', $base->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['nombre'])->toBe('Base Norte');

    $this->put(
        route('panel.bases.update', $base),
        payloadBase(['nombre' => 'Base Norte B']),
    )->assertRedirect(route('panel.bases.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'per_bases')
        ->where('registro_id', $base->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['nombre'])->toBe('Base Norte B');

    $this->delete(route('panel.bases.destroy', $base))
        ->assertRedirect(route('panel.bases.index'));

    Bitacora::query()
        ->where('tabla', 'per_bases')
        ->where('registro_id', $base->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja una base por soft delete: no aparece en el índice y un segundo intento da 404', function () {
    [$encargado, $idRol] = usuarioConRolParaBases('encargado', 'encargado_operaciones');
    entrarAlPanelParaBases($encargado, $idRol);

    $this->post(route('panel.bases.store'), payloadBase());
    $base = PerBase::query()->sole();

    $this->delete(route('panel.bases.destroy', $base))
        ->assertRedirect(route('panel.bases.index'));

    $borrado = PerBase::withTrashed()->findOrFail($base->id);
    expect($borrado->trashed())->toBeTrue();

    $this->get(route('panel.bases.index'))
        ->assertOk()
        ->assertDontSee('Base Norte');

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.bases.destroy', $base))->assertNotFound();
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaBases('piloto.curioso', 'piloto');
    entrarAlPanelParaBases($piloto, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Existente']);

    $this->get(route('panel.bases.index'))->assertForbidden();
    $this->get(route('panel.bases.create'))->assertForbidden();
    $this->post(route('panel.bases.store'), payloadBase())->assertForbidden();
    $this->get(route('panel.bases.edit', $base))->assertForbidden();
    $this->put(route('panel.bases.update', $base), payloadBase())->assertForbidden();
    $this->delete(route('panel.bases.destroy', $base))->assertForbidden();

    expect(PerBase::query()->count())->toBe(1)
        ->and($base->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    [$multirol, $idEncargado] = usuarioConRolParaBases('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaBases($multirol, $idPiloto);
    $this->post(route('panel.bases.store'), payloadBase())->assertForbidden();
    expect(PerBase::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaBases($multirol, $idEncargado);
    $this->post(route('panel.bases.store'), payloadBase())->assertRedirect();
    expect(PerBase::query()->count())->toBe(1);
});

it('publica el ítem de menú de bases gateado por personal.base.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.recursos.items.bases')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'personal.base.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.bases.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
