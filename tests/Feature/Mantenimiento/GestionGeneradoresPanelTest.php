<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
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
 * Tarea 72 (HU-49, ADR 0015 punto 3): ABM mínimo del catálogo de
 * generadores — no es una HU propia, es la tabla que hace falta para poder
 * asignar un generador como equipamiento de un equipo de trabajo. Permisos
 * evaluados contra el ROL ACTIVO de la sesión, nunca la unión de los roles
 * del usuario (invariante 10 de CLAUDE.md). Mismo patrón de asserts que
 * tests/Feature/Mantenimiento/GestionVehiculosPanelTest.php (tarea 50).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaGeneradores(string $username, string $rol): array
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
function entrarAlPanelParaGeneradores(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de alta/edición. */
function payloadGenerador(array $overrides = []): array
{
    return array_merge([
        'identificador' => 'GEN-001',
        'modelo' => '',
        'base_id' => '',
        'estado' => 'activo',
        'horas_uso' => '',
    ], $overrides);
}

it('da de alta un generador con identificador, modelo, base, estado y horas de uso válidos', function () {
    [$encargado, $idRol] = usuarioConRolParaGeneradores('encargado', 'encargado_operaciones');
    entrarAlPanelParaGeneradores($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);

    $this->post(route('panel.generadores.store'), payloadGenerador([
        'modelo' => 'Honda EU3000',
        'base_id' => (string) $base->id,
        'estado' => 'taller',
        'horas_uso' => '120.50',
    ]))->assertRedirect(route('panel.generadores.index'));

    $generador = Generador::query()->where('identificador', 'GEN-001')->sole();

    expect($generador->base_id)->toBe($base->id)
        ->and($generador->estado)->toBe('taller')
        ->and($generador->modelo)->toBe('Honda EU3000')
        ->and((float) $generador->horas_uso)->toBe(120.50);
});

it('da de alta un generador sin modelo, base ni horas de uso', function () {
    [$encargado, $idRol] = usuarioConRolParaGeneradores('encargado', 'encargado_operaciones');
    entrarAlPanelParaGeneradores($encargado, $idRol);

    $this->post(route('panel.generadores.store'), payloadGenerador())
        ->assertRedirect(route('panel.generadores.index'));

    $generador = Generador::query()->where('identificador', 'GEN-001')->sole();
    expect($generador->base_id)->toBeNull()
        ->and($generador->modelo)->toBeNull()
        ->and($generador->horas_uso)->toBeNull()
        ->and($generador->estado)->toBe('activo');
});

it('rechaza un estado fuera del enum sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaGeneradores('encargado', 'encargado_operaciones');
    entrarAlPanelParaGeneradores($encargado, $idRol);

    $this->post(route('panel.generadores.store'), payloadGenerador(['estado' => 'volando']))
        ->assertSessionHasErrors('estado');

    expect(Generador::query()->where('identificador', 'GEN-001')->exists())->toBeFalse();
});

it('rechaza una base_id inexistente sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaGeneradores('encargado', 'encargado_operaciones');
    entrarAlPanelParaGeneradores($encargado, $idRol);

    $this->post(route('panel.generadores.store'), payloadGenerador(['base_id' => '999999']))
        ->assertSessionHasErrors('base_id');

    expect(Generador::query()->where('identificador', 'GEN-001')->exists())->toBeFalse();
});

it('rechaza horas de uso negativas sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaGeneradores('encargado', 'encargado_operaciones');
    entrarAlPanelParaGeneradores($encargado, $idRol);

    $this->post(route('panel.generadores.store'), payloadGenerador(['horas_uso' => '-5']))
        ->assertSessionHasErrors('horas_uso');

    expect(Generador::query()->where('identificador', 'GEN-001')->exists())->toBeFalse();
});

it('el identificador duplicado entre generadores activos es un error de validación, no un QueryException', function () {
    [$encargado, $idRol] = usuarioConRolParaGeneradores('encargado', 'encargado_operaciones');
    entrarAlPanelParaGeneradores($encargado, $idRol);

    Generador::query()->create(['identificador' => 'GEN-001', 'estado' => 'activo']);

    $this->post(route('panel.generadores.store'), payloadGenerador(['estado' => 'taller']))
        ->assertSessionHasErrors('identificador');

    expect(Generador::query()->where('estado', 'taller')->exists())->toBeFalse();
});

it('un generador dado de baja no bloquea el re-alta con el mismo identificador', function () {
    [$encargado, $idRol] = usuarioConRolParaGeneradores('encargado', 'encargado_operaciones');
    entrarAlPanelParaGeneradores($encargado, $idRol);

    $existente = Generador::query()->create(['identificador' => 'GEN-001', 'estado' => 'activo']);
    $existente->delete();

    $this->post(route('panel.generadores.store'), payloadGenerador())
        ->assertRedirect(route('panel.generadores.index'));

    expect(Generador::query()->where('identificador', 'GEN-001')->count())->toBe(1);
});

it('edita un generador existente, incluida su asignación de base', function () {
    [$encargado, $idRol] = usuarioConRolParaGeneradores('encargado', 'encargado_operaciones');
    entrarAlPanelParaGeneradores($encargado, $idRol);

    $baseVieja = PerBase::query()->create(['nombre' => 'Base Norte']);
    $baseNueva = PerBase::query()->create(['nombre' => 'Base Sur']);

    $generador = Generador::query()->create(['identificador' => 'GEN-001', 'base_id' => $baseVieja->id, 'estado' => 'activo']);

    $this->put(
        route('panel.generadores.update', $generador),
        payloadGenerador(['identificador' => 'GEN-001-B', 'base_id' => (string) $baseNueva->id, 'estado' => 'de_baja']),
    )->assertRedirect(route('panel.generadores.index'));

    $generador->refresh();
    expect($generador->identificador)->toBe('GEN-001-B')
        ->and($generador->base_id)->toBe($baseNueva->id)
        ->and($generador->estado)->toBe('de_baja');
});

it('filtra el listado por base y por estado', function () {
    [$encargado, $idRol] = usuarioConRolParaGeneradores('encargado', 'encargado_operaciones');
    entrarAlPanelParaGeneradores($encargado, $idRol);

    $baseNorte = PerBase::query()->create(['nombre' => 'Base Norte']);
    $baseSur = PerBase::query()->create(['nombre' => 'Base Sur']);

    Generador::query()->create(['identificador' => 'GEN-NORTE', 'base_id' => $baseNorte->id, 'estado' => 'activo']);
    Generador::query()->create(['identificador' => 'GEN-SUR', 'base_id' => $baseSur->id, 'estado' => 'taller']);

    $this->get(route('panel.generadores.index', ['base_id' => $baseNorte->id]))
        ->assertOk()
        ->assertSee('GEN-NORTE')
        ->assertDontSee('GEN-SUR');

    $this->get(route('panel.generadores.index', ['estado' => 'taller']))
        ->assertOk()
        ->assertSee('GEN-SUR')
        ->assertDontSee('GEN-NORTE');
});

it('registra en bitácora el alta, la edición y la baja de un generador', function () {
    [$encargado, $idRol] = usuarioConRolParaGeneradores('encargado', 'encargado_operaciones');
    entrarAlPanelParaGeneradores($encargado, $idRol);

    $this->post(route('panel.generadores.store'), payloadGenerador());

    $generador = Generador::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'man_generadores')
        ->where('registro_id', $generador->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['identificador'])->toBe('GEN-001');

    $this->put(
        route('panel.generadores.update', $generador),
        payloadGenerador(['identificador' => 'GEN-001-B']),
    )->assertRedirect(route('panel.generadores.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'man_generadores')
        ->where('registro_id', $generador->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['identificador'])->toBe('GEN-001-B');

    $this->delete(route('panel.generadores.destroy', $generador))
        ->assertRedirect(route('panel.generadores.index'));

    Bitacora::query()
        ->where('tabla', 'man_generadores')
        ->where('registro_id', $generador->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja un generador por soft delete: no aparece en el índice y un segundo intento da 404', function () {
    [$encargado, $idRol] = usuarioConRolParaGeneradores('encargado', 'encargado_operaciones');
    entrarAlPanelParaGeneradores($encargado, $idRol);

    $this->post(route('panel.generadores.store'), payloadGenerador());
    $generador = Generador::query()->sole();

    $this->delete(route('panel.generadores.destroy', $generador))
        ->assertRedirect(route('panel.generadores.index'));

    $borrado = Generador::withTrashed()->findOrFail($generador->id);
    expect($borrado->trashed())->toBeTrue();

    $this->get(route('panel.generadores.index'))
        ->assertOk()
        ->assertDontSee('GEN-001');

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.generadores.destroy', $generador))->assertNotFound();
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaGeneradores('piloto.curioso', 'piloto');
    entrarAlPanelParaGeneradores($piloto, $idRol);

    $generador = Generador::query()->create(['identificador' => 'GEN-EXISTENTE', 'estado' => 'activo']);

    $this->get(route('panel.generadores.index'))->assertForbidden();
    $this->get(route('panel.generadores.create'))->assertForbidden();
    $this->post(route('panel.generadores.store'), payloadGenerador())->assertForbidden();
    $this->get(route('panel.generadores.edit', $generador))->assertForbidden();
    $this->put(route('panel.generadores.update', $generador), payloadGenerador())->assertForbidden();
    $this->delete(route('panel.generadores.destroy', $generador))->assertForbidden();

    expect(Generador::query()->count())->toBe(1)
        ->and($generador->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    [$multirol, $idEncargado] = usuarioConRolParaGeneradores('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaGeneradores($multirol, $idPiloto);
    $this->post(route('panel.generadores.store'), payloadGenerador())->assertForbidden();
    expect(Generador::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaGeneradores($multirol, $idEncargado);
    $this->post(route('panel.generadores.store'), payloadGenerador())->assertRedirect();
    expect(Generador::query()->count())->toBe(1);
});

it('publica el ítem de menú de generadores gateado por mantenimiento.generador.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.recursos.items.generadores')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'mantenimiento.generador.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.generadores.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
