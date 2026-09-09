<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-48 (tarea 71, ADR 0015 punto 4): catálogo de cultivos. Permisos
 * evaluados contra el ROL ACTIVO de la sesión, nunca la unión de los roles
 * del usuario (invariante 10 de CLAUDE.md). Mismo patrón de asserts que
 * tests/Feature/Personal/GestionBasesPanelTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaCultivos(string $username, string $rol): array
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
function entrarAlPanelParaCultivos(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de alta/edición. */
function payloadCultivo(array $overrides = []): array
{
    return array_merge([
        'nombre' => 'Avena',
        'activo' => '1',
    ], $overrides);
}

it('el seeder de catálogo siembra los cultivos de la zona una sola vez tras dos corridas', function () {
    $this->seed(CatalogoSeeder::class);

    expect(Cultivo::query()->count())->toBe(7)
        ->and(Cultivo::query()->where('nombre', 'Soya')->exists())->toBeTrue()
        ->and(Cultivo::query()->where('nombre', 'Maíz')->exists())->toBeTrue();
});

it('da de alta un cultivo activo por defecto', function () {
    [$encargado, $idRol] = usuarioConRolParaCultivos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCultivos($encargado, $idRol);

    $this->post(route('panel.cultivos.store'), payloadCultivo())
        ->assertRedirect(route('panel.cultivos.index'));

    $cultivo = Cultivo::query()->where('nombre', 'Avena')->sole();

    expect($cultivo->activo)->toBeTrue();
});

it('registra en bitácora el alta, la edición y la baja de un cultivo', function () {
    [$encargado, $idRol] = usuarioConRolParaCultivos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCultivos($encargado, $idRol);

    $this->post(route('panel.cultivos.store'), payloadCultivo());

    $cultivo = Cultivo::query()->where('nombre', 'Avena')->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'com_cultivos')
        ->where('registro_id', $cultivo->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['nombre'])->toBe('Avena');

    $this->put(
        route('panel.cultivos.update', $cultivo),
        payloadCultivo(['nombre' => 'Avena forrajera']),
    )->assertRedirect(route('panel.cultivos.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'com_cultivos')
        ->where('registro_id', $cultivo->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['nombre'])->toBe('Avena forrajera');

    $this->delete(route('panel.cultivos.destroy', $cultivo))
        ->assertRedirect(route('panel.cultivos.index'));

    Bitacora::query()
        ->where('tabla', 'com_cultivos')
        ->where('registro_id', $cultivo->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja un cultivo por soft delete: no aparece en el índice y un segundo intento da 404', function () {
    [$encargado, $idRol] = usuarioConRolParaCultivos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCultivos($encargado, $idRol);

    $this->post(route('panel.cultivos.store'), payloadCultivo());
    $cultivo = Cultivo::query()->where('nombre', 'Avena')->sole();

    $this->delete(route('panel.cultivos.destroy', $cultivo))
        ->assertRedirect(route('panel.cultivos.index'));

    $borrado = Cultivo::withTrashed()->findOrFail($cultivo->id);
    expect($borrado->trashed())->toBeTrue();

    $this->get(route('panel.cultivos.index'))
        ->assertOk()
        ->assertDontSee('Avena');

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.cultivos.destroy', $cultivo))->assertNotFound();
});

it('el nombre de cultivo duplicado entre cultivos activos es un error de validación, no un QueryException', function () {
    [$encargado, $idRol] = usuarioConRolParaCultivos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCultivos($encargado, $idRol);

    $this->post(route('panel.cultivos.store'), payloadCultivo(['nombre' => 'Soya']))
        ->assertSessionHasErrors('nombre');

    expect(Cultivo::query()->where('nombre', 'Soya')->count())->toBe(1);
});

it('el mismo nombre puede reutilizarse tras dar de baja el cultivo anterior', function () {
    [$encargado, $idRol] = usuarioConRolParaCultivos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCultivos($encargado, $idRol);

    $this->post(route('panel.cultivos.store'), payloadCultivo(['nombre' => 'Avena']));
    $original = Cultivo::query()->where('nombre', 'Avena')->sole();
    $this->delete(route('panel.cultivos.destroy', $original));

    $this->post(route('panel.cultivos.store'), payloadCultivo(['nombre' => 'Avena']))
        ->assertRedirect(route('panel.cultivos.index'));

    expect(Cultivo::query()->where('nombre', 'Avena')->count())->toBe(1);
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaCultivos('piloto.curioso', 'piloto');
    entrarAlPanelParaCultivos($piloto, $idRol);

    $cultivo = Cultivo::query()->where('nombre', 'Soya')->sole();

    $this->get(route('panel.cultivos.index'))->assertForbidden();
    $this->get(route('panel.cultivos.create'))->assertForbidden();
    $this->post(route('panel.cultivos.store'), payloadCultivo())->assertForbidden();
    $this->get(route('panel.cultivos.edit', $cultivo))->assertForbidden();
    $this->put(route('panel.cultivos.update', $cultivo), payloadCultivo())->assertForbidden();
    $this->delete(route('panel.cultivos.destroy', $cultivo))->assertForbidden();

    expect(Cultivo::query()->where('nombre', 'Avena')->exists())->toBeFalse()
        ->and($cultivo->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    [$multirol, $idEncargado] = usuarioConRolParaCultivos('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaCultivos($multirol, $idPiloto);
    $this->post(route('panel.cultivos.store'), payloadCultivo())->assertForbidden();
    expect(Cultivo::query()->where('nombre', 'Avena')->exists())->toBeFalse();

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaCultivos($multirol, $idEncargado);
    $this->post(route('panel.cultivos.store'), payloadCultivo())->assertRedirect();
    expect(Cultivo::query()->where('nombre', 'Avena')->exists())->toBeTrue();
});

it('publica el ítem de menú de cultivos gateado por comercial.cultivo.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.comercial.items.cultivos')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'comercial.cultivo.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.cultivos.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
