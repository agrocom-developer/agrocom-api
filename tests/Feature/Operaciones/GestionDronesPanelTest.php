<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-27 (tarea 36): administración de la flota de drones, con su modelo y
 * capacidad de carga. Permisos evaluados contra el ROL ACTIVO de la sesión,
 * nunca la unión de los roles del usuario (invariante 10 de CLAUDE.md).
 * Mismo patrón de asserts que
 * tests/Feature/Comercial/GestionClientesPanelTest.php (tarea 33).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaDrones(string $username, string $rol): array
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
function entrarAlPanelParaDrones(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de alta/edición. */
function payloadDron(array $overrides = []): array
{
    return array_merge([
        'identificador' => 'DRN-001',
        'modelo' => 'DJI Agras T30',
        'capacidad_l' => 30,
    ], $overrides);
}

it('da de alta un dron con identificador, modelo y capacidad válidos', function () {
    [$encargado, $idRol] = usuarioConRolParaDrones('encargado', 'encargado_operaciones');
    entrarAlPanelParaDrones($encargado, $idRol);

    $this->post(route('panel.drones.store'), payloadDron())
        ->assertRedirect(route('panel.drones.index'));

    $dron = Dron::query()->where('identificador', 'DRN-001')->sole();

    expect($dron->modelo)->toBe('DJI Agras T30')
        ->and((int) $dron->capacidad_l)->toBe(30);
});

it('rechaza una capacidad fuera de {30, 50, 60} sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaDrones('encargado', 'encargado_operaciones');
    entrarAlPanelParaDrones($encargado, $idRol);

    $this->post(route('panel.drones.store'), payloadDron(['capacidad_l' => 45]))
        ->assertSessionHasErrors('capacidad_l');

    expect(Dron::query()->where('identificador', 'DRN-001')->exists())->toBeFalse();
});

it('guarda y lee capacidad_kg desde el alta y desde la edición de un dron', function () {
    [$encargado, $idRol] = usuarioConRolParaDrones('encargado', 'encargado_operaciones');
    entrarAlPanelParaDrones($encargado, $idRol);

    $this->post(route('panel.drones.store'), payloadDron(['capacidad_kg' => 12.5]))
        ->assertRedirect(route('panel.drones.index'));

    $dron = Dron::query()->where('identificador', 'DRN-001')->sole();
    expect((float) $dron->capacidad_kg)->toBe(12.5);

    $this->put(
        route('panel.drones.update', $dron),
        payloadDron(['capacidad_kg' => 500]),
    )->assertRedirect(route('panel.drones.index'));

    expect((float) $dron->fresh()->capacidad_kg)->toBe(500.0);
});

it('un dron sin capacidad_kg (NULL) sigue operando líquido sin cambios: alta y edición con solo capacidad_l', function () {
    [$encargado, $idRol] = usuarioConRolParaDrones('encargado', 'encargado_operaciones');
    entrarAlPanelParaDrones($encargado, $idRol);

    $this->post(route('panel.drones.store'), payloadDron())
        ->assertRedirect(route('panel.drones.index'));

    $dron = Dron::query()->where('identificador', 'DRN-001')->sole();
    expect($dron->capacidad_kg)->toBeNull()
        ->and((int) $dron->capacidad_l)->toBe(30);

    $this->put(
        route('panel.drones.update', $dron),
        payloadDron(['identificador' => 'DRN-001-B']),
    )->assertRedirect(route('panel.drones.index'));

    $dron->refresh();
    expect($dron->capacidad_kg)->toBeNull()
        ->and((int) $dron->capacidad_l)->toBe(30)
        ->and($dron->identificador)->toBe('DRN-001-B');
});

it('rechaza capacidad_kg negativo o cero sin persistir, en alta y en edición', function () {
    [$encargado, $idRol] = usuarioConRolParaDrones('encargado', 'encargado_operaciones');
    entrarAlPanelParaDrones($encargado, $idRol);

    $this->post(route('panel.drones.store'), payloadDron(['capacidad_kg' => 0]))
        ->assertSessionHasErrors('capacidad_kg');
    $this->post(route('panel.drones.store'), payloadDron(['capacidad_kg' => -5]))
        ->assertSessionHasErrors('capacidad_kg');

    expect(Dron::query()->where('identificador', 'DRN-001')->exists())->toBeFalse();

    $dron = Dron::query()->create(['identificador' => 'DRN-001', 'capacidad_l' => 30]);

    $this->put(
        route('panel.drones.update', $dron),
        payloadDron(['capacidad_kg' => 0]),
    )->assertSessionHasErrors('capacidad_kg');

    expect($dron->fresh()->capacidad_kg)->toBeNull();
});

it('el identificador duplicado entre drones activos es un error de validación, no un QueryException', function () {
    [$encargado, $idRol] = usuarioConRolParaDrones('encargado', 'encargado_operaciones');
    entrarAlPanelParaDrones($encargado, $idRol);

    Dron::query()->create(['identificador' => 'DRN-001']);

    $this->post(route('panel.drones.store'), payloadDron([
        'modelo' => 'DJI Agras T40',
    ]))->assertSessionHasErrors('identificador');

    expect(Dron::query()->where('modelo', 'DJI Agras T40')->exists())->toBeFalse();
});

it('registra en bitácora el alta, la edición y la baja de un dron', function () {
    [$encargado, $idRol] = usuarioConRolParaDrones('encargado', 'encargado_operaciones');
    entrarAlPanelParaDrones($encargado, $idRol);

    $this->post(route('panel.drones.store'), payloadDron());

    $dron = Dron::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'ope_drones')
        ->where('registro_id', $dron->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['identificador'])->toBe('DRN-001');

    $this->put(
        route('panel.drones.update', $dron),
        payloadDron(['identificador' => 'DRN-001-B']),
    )->assertRedirect(route('panel.drones.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'ope_drones')
        ->where('registro_id', $dron->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['identificador'])->toBe('DRN-001-B');

    $this->delete(route('panel.drones.destroy', $dron))
        ->assertRedirect(route('panel.drones.index'));

    Bitacora::query()
        ->where('tabla', 'ope_drones')
        ->where('registro_id', $dron->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja un dron por soft delete: no aparece en el índice y un segundo intento da 404', function () {
    [$encargado, $idRol] = usuarioConRolParaDrones('encargado', 'encargado_operaciones');
    entrarAlPanelParaDrones($encargado, $idRol);

    $this->post(route('panel.drones.store'), payloadDron());
    $dron = Dron::query()->sole();

    $this->delete(route('panel.drones.destroy', $dron))
        ->assertRedirect(route('panel.drones.index'));

    $borrado = Dron::withTrashed()->findOrFail($dron->id);
    expect($borrado->trashed())->toBeTrue();

    $this->get(route('panel.drones.index'))
        ->assertOk()
        ->assertDontSee('DRN-001');

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.drones.destroy', $dron))->assertNotFound();
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaDrones('piloto.curioso', 'piloto');
    entrarAlPanelParaDrones($piloto, $idRol);

    $dron = Dron::query()->create(['identificador' => 'DRN-EXISTENTE']);

    $this->get(route('panel.drones.index'))->assertForbidden();
    $this->get(route('panel.drones.create'))->assertForbidden();
    $this->post(route('panel.drones.store'), payloadDron())->assertForbidden();
    $this->get(route('panel.drones.edit', $dron))->assertForbidden();
    $this->put(route('panel.drones.update', $dron), payloadDron())->assertForbidden();
    $this->delete(route('panel.drones.destroy', $dron))->assertForbidden();

    expect(Dron::query()->count())->toBe(1)
        ->and($dron->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    [$multirol, $idEncargado] = usuarioConRolParaDrones('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaDrones($multirol, $idPiloto);
    $this->post(route('panel.drones.store'), payloadDron())->assertForbidden();
    expect(Dron::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaDrones($multirol, $idEncargado);
    $this->post(route('panel.drones.store'), payloadDron())->assertRedirect();
    expect(Dron::query()->count())->toBe(1);
});

it('publica el ítem de menú de drones gateado por operaciones.dron.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.recursos.items.drones')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'operaciones.dron.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.drones.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
