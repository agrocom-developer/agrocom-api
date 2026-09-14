<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\FichaDron;
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
 * HU-82 (tarea 97): ficha de inventario del dron — serie, chasis, versión de
 * software, región, serie del control y accesorios. Segundo ABM del módulo
 * `Mantenimiento` sin máquina de estados (el primero fue `man_baterias`).
 * Permisos evaluados contra el ROL ACTIVO de la sesión, nunca la unión de
 * los roles del usuario (invariante 10 de CLAUDE.md). Mismo patrón de
 * asserts que tests/Feature/Mantenimiento/GestionBateriasPanelTest.php.
 *
 * `identificador_dron` valida contra un dron ACTIVO real de `ope_drones`
 * (`Rule::exists()`) — cada test que da de alta o edita crea primero su
 * propio `Dron` de `Operaciones`, mismo patrón que
 * tests/Feature/Mantenimiento/OrdenesMantenimientoPanelTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaFichasDron(string $username, string $rol): array
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
function entrarAlPanelParaFichasDron(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de alta/edición. */
function payloadFichaDron(array $overrides = []): array
{
    return array_merge([
        'identificador_dron' => 'DRN-001',
        'numero_serie' => 'SN-12345',
        'chasis' => 'CH-987',
        'version_software' => '4.2.1',
        'region' => 'AME',
        'serie_control' => 'CTRL-555',
        'tiene_cargador_control' => '1',
        'tiene_modem' => '1',
        'tiene_maletin' => '1',
    ], $overrides);
}

it('da de alta una ficha de dron para un dron activo real', function () {
    [$encargado, $idRol] = usuarioConRolParaFichasDron('encargado', 'encargado_operaciones');
    entrarAlPanelParaFichasDron($encargado, $idRol);

    Dron::query()->create(['identificador' => 'DRN-001']);

    $this->post(route('panel.fichas-dron.store'), payloadFichaDron())
        ->assertRedirect(route('panel.fichas-dron.index'));

    $ficha = FichaDron::query()->where('identificador_dron', 'DRN-001')->sole();

    expect($ficha->numero_serie)->toBe('SN-12345')
        ->and($ficha->chasis)->toBe('CH-987')
        ->and($ficha->version_software)->toBe('4.2.1')
        ->and($ficha->region)->toBe('AME')
        ->and($ficha->serie_control)->toBe('CTRL-555')
        ->and($ficha->tiene_cargador_control)->toBeTrue()
        ->and($ficha->tiene_modem)->toBeTrue()
        ->and($ficha->tiene_maletin)->toBeTrue();
});

it('da de alta una ficha de dron sin accesorios marcados', function () {
    [$encargado, $idRol] = usuarioConRolParaFichasDron('encargado', 'encargado_operaciones');
    entrarAlPanelParaFichasDron($encargado, $idRol);

    Dron::query()->create(['identificador' => 'DRN-001']);

    $payload = payloadFichaDron();
    unset($payload['tiene_cargador_control'], $payload['tiene_modem'], $payload['tiene_maletin']);

    $this->post(route('panel.fichas-dron.store'), $payload)
        ->assertRedirect(route('panel.fichas-dron.index'));

    $ficha = FichaDron::query()->where('identificador_dron', 'DRN-001')->sole();
    expect($ficha->tiene_cargador_control)->toBeFalse()
        ->and($ficha->tiene_modem)->toBeFalse()
        ->and($ficha->tiene_maletin)->toBeFalse();
});

it('rechaza un identificador_dron que no corresponde a ningún dron activo, sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaFichasDron('encargado', 'encargado_operaciones');
    entrarAlPanelParaFichasDron($encargado, $idRol);

    $this->post(route('panel.fichas-dron.store'), payloadFichaDron(['identificador_dron' => 'DRN-INEXISTENTE']))
        ->assertSessionHasErrors('identificador_dron');

    expect(FichaDron::query()->where('identificador_dron', 'DRN-INEXISTENTE')->exists())->toBeFalse();

    // Mismo caso, en un request que pide JSON: 422, no un redirect — el
    // criterio de aceptación de la HU pide explícitamente este código.
    $this->postJson(route('panel.fichas-dron.store'), payloadFichaDron(['identificador_dron' => 'DRN-INEXISTENTE']))
        ->assertStatus(422);
});

it('rechaza un identificador_dron de un dron dado de baja lógica en ope_drones', function () {
    [$encargado, $idRol] = usuarioConRolParaFichasDron('encargado', 'encargado_operaciones');
    entrarAlPanelParaFichasDron($encargado, $idRol);

    $dron = Dron::query()->create(['identificador' => 'DRN-001']);
    $dron->delete();

    $this->post(route('panel.fichas-dron.store'), payloadFichaDron())
        ->assertSessionHasErrors('identificador_dron');

    expect(FichaDron::query()->where('identificador_dron', 'DRN-001')->exists())->toBeFalse();
});

it('el identificador_dron duplicado entre fichas activas es un error de validación, no un QueryException', function () {
    [$encargado, $idRol] = usuarioConRolParaFichasDron('encargado', 'encargado_operaciones');
    entrarAlPanelParaFichasDron($encargado, $idRol);

    Dron::query()->create(['identificador' => 'DRN-001']);
    FichaDron::query()->create(['identificador_dron' => 'DRN-001']);

    $this->post(route('panel.fichas-dron.store'), payloadFichaDron())
        ->assertSessionHasErrors('identificador_dron');

    expect(FichaDron::query()->where('identificador_dron', 'DRN-001')->count())->toBe(1);
});

it('edita una ficha existente, incluido su identificador_dron', function () {
    [$encargado, $idRol] = usuarioConRolParaFichasDron('encargado', 'encargado_operaciones');
    entrarAlPanelParaFichasDron($encargado, $idRol);

    Dron::query()->create(['identificador' => 'DRN-001']);
    Dron::query()->create(['identificador' => 'DRN-002']);
    $ficha = FichaDron::query()->create(['identificador_dron' => 'DRN-001', 'numero_serie' => 'SN-OLD']);

    $this->put(
        route('panel.fichas-dron.update', $ficha),
        payloadFichaDron(['identificador_dron' => 'DRN-002', 'numero_serie' => 'SN-NEW']),
    )->assertRedirect(route('panel.fichas-dron.index'));

    $ficha->refresh();
    expect($ficha->identificador_dron)->toBe('DRN-002')
        ->and($ficha->numero_serie)->toBe('SN-NEW');
});

it('filtra el listado por identificador_dron', function () {
    [$encargado, $idRol] = usuarioConRolParaFichasDron('encargado', 'encargado_operaciones');
    entrarAlPanelParaFichasDron($encargado, $idRol);

    FichaDron::query()->create(['identificador_dron' => 'DRN-NORTE']);
    FichaDron::query()->create(['identificador_dron' => 'DRN-SUR']);

    $this->get(route('panel.fichas-dron.index', ['q' => 'NORTE']))
        ->assertOk()
        ->assertSee('DRN-NORTE')
        ->assertDontSee('DRN-SUR');
});

it('muestra el formulario de alta y de edición', function () {
    [$encargado, $idRol] = usuarioConRolParaFichasDron('encargado', 'encargado_operaciones');
    entrarAlPanelParaFichasDron($encargado, $idRol);

    $this->get(route('panel.fichas-dron.create'))->assertOk();

    Dron::query()->create(['identificador' => 'DRN-001']);
    $ficha = FichaDron::query()->create(['identificador_dron' => 'DRN-001', 'numero_serie' => 'SN-123']);

    $this->get(route('panel.fichas-dron.edit', $ficha))
        ->assertOk()
        ->assertSee('DRN-001')
        ->assertSee('SN-123');
});

it('la baja de la ficha no aparece más en el índice y no modifica ni borra la fila de ope_drones', function () {
    [$encargado, $idRol] = usuarioConRolParaFichasDron('encargado', 'encargado_operaciones');
    entrarAlPanelParaFichasDron($encargado, $idRol);

    $dron = Dron::query()->create(['identificador' => 'DRN-001', 'modelo' => 'DJI Agras T30']);
    $dronOriginal = $dron->replicate();

    $this->post(route('panel.fichas-dron.store'), payloadFichaDron());
    $ficha = FichaDron::query()->sole();

    $this->delete(route('panel.fichas-dron.destroy', $ficha))
        ->assertRedirect(route('panel.fichas-dron.index'));

    $this->get(route('panel.fichas-dron.index'))
        ->assertOk()
        ->assertDontSee('DRN-001');

    // La ficha se borró (lógicamente); la fila de `ope_drones` con el mismo
    // identificador, ajena a este módulo, sigue exactamente igual.
    $dronTrasLaBaja = Dron::query()->findOrFail($dron->id);
    expect($dronTrasLaBaja->identificador)->toBe($dronOriginal->identificador)
        ->and($dronTrasLaBaja->modelo)->toBe($dronOriginal->modelo)
        ->and($dronTrasLaBaja->trashed())->toBeFalse();
});

it('registra en bitácora el alta, la edición y la baja de una ficha de dron', function () {
    [$encargado, $idRol] = usuarioConRolParaFichasDron('encargado', 'encargado_operaciones');
    entrarAlPanelParaFichasDron($encargado, $idRol);

    Dron::query()->create(['identificador' => 'DRN-001']);

    $this->post(route('panel.fichas-dron.store'), payloadFichaDron());

    $ficha = FichaDron::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'man_drones')
        ->where('registro_id', $ficha->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['identificador_dron'])->toBe('DRN-001');

    $this->put(
        route('panel.fichas-dron.update', $ficha),
        payloadFichaDron(['numero_serie' => 'SN-CAMBIADO']),
    )->assertRedirect(route('panel.fichas-dron.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'man_drones')
        ->where('registro_id', $ficha->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['numero_serie'])->toBe('SN-CAMBIADO');

    $this->delete(route('panel.fichas-dron.destroy', $ficha))
        ->assertRedirect(route('panel.fichas-dron.index'));

    Bitacora::query()
        ->where('tabla', 'man_drones')
        ->where('registro_id', $ficha->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja una ficha por soft delete vía panel: un segundo intento da 404', function () {
    [$encargado, $idRol] = usuarioConRolParaFichasDron('encargado', 'encargado_operaciones');
    entrarAlPanelParaFichasDron($encargado, $idRol);

    Dron::query()->create(['identificador' => 'DRN-001']);
    $this->post(route('panel.fichas-dron.store'), payloadFichaDron());
    $ficha = FichaDron::query()->sole();

    $this->delete(route('panel.fichas-dron.destroy', $ficha))
        ->assertRedirect(route('panel.fichas-dron.index'));

    $borrada = FichaDron::withTrashed()->findOrFail($ficha->id);
    expect($borrada->trashed())->toBeTrue();

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.fichas-dron.destroy', $ficha))->assertNotFound();
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaFichasDron('piloto.curioso', 'piloto');
    entrarAlPanelParaFichasDron($piloto, $idRol);

    Dron::query()->create(['identificador' => 'DRN-001']);
    $ficha = FichaDron::query()->create(['identificador_dron' => 'DRN-EXISTENTE']);

    $this->get(route('panel.fichas-dron.index'))->assertForbidden();
    $this->get(route('panel.fichas-dron.create'))->assertForbidden();
    $this->post(route('panel.fichas-dron.store'), payloadFichaDron())->assertForbidden();
    $this->get(route('panel.fichas-dron.edit', $ficha))->assertForbidden();
    $this->put(route('panel.fichas-dron.update', $ficha), payloadFichaDron())->assertForbidden();
    $this->delete(route('panel.fichas-dron.destroy', $ficha))->assertForbidden();

    expect(FichaDron::query()->count())->toBe(1)
        ->and($ficha->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    [$multirol, $idEncargado] = usuarioConRolParaFichasDron('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    Dron::query()->create(['identificador' => 'DRN-001']);

    entrarAlPanelParaFichasDron($multirol, $idPiloto);
    $this->post(route('panel.fichas-dron.store'), payloadFichaDron())->assertForbidden();
    expect(FichaDron::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaFichasDron($multirol, $idEncargado);
    $this->post(route('panel.fichas-dron.store'), payloadFichaDron())->assertRedirect();
    expect(FichaDron::query()->count())->toBe(1);
});

it('publica el ítem de menú de fichas de dron gateado por mantenimiento.ficha_dron.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.recursos.items.fichas_dron')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'mantenimiento.ficha_dron.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.fichas-dron.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
