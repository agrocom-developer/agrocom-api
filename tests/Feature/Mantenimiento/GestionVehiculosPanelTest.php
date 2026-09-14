<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
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
 * HU-40 (tarea 50): administración de la flota de vehículos, con su
 * asignación a base y estado. Primer ABM del módulo `Mantenimiento` (ADR
 * 0011, extensión 3/9/2026). Permisos evaluados contra el ROL ACTIVO de la
 * sesión, nunca la unión de los roles del usuario (invariante 10 de
 * CLAUDE.md). Mismo patrón de asserts que
 * tests/Feature/Operaciones/GestionDronesPanelTest.php (tarea 36).
 *
 * HU-84 (tarea 99) suma la ficha completa (marca, modelo, año, combustible,
 * 4x4, kilometraje inicial y actual) y el estado `pausa` — ver los tests
 * agregados más abajo.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaVehiculos(string $username, string $rol): array
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
function entrarAlPanelParaVehiculos(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de alta/edición. */
function payloadVehiculo(array $overrides = []): array
{
    return array_merge([
        'identificador' => 'VHC-001',
        'marca' => '',
        'modelo' => '',
        'anio' => '',
        'combustible' => '',
        'es_4x4' => '0',
        'kilometraje_inicial' => '',
        'kilometraje_actual' => '',
        'base_id' => '',
        'estado' => 'activo',
    ], $overrides);
}

it('da de alta un vehículo con identificador, base y estado válidos', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);

    $this->post(route('panel.vehiculos.store'), payloadVehiculo(['base_id' => (string) $base->id, 'estado' => 'taller']))
        ->assertRedirect(route('panel.vehiculos.index'));

    $vehiculo = Vehiculo::query()->where('identificador', 'VHC-001')->sole();

    expect($vehiculo->base_id)->toBe($base->id)
        ->and($vehiculo->estado)->toBe('taller');
});

it('da de alta un vehículo sin base asignada', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    $this->post(route('panel.vehiculos.store'), payloadVehiculo())
        ->assertRedirect(route('panel.vehiculos.index'));

    $vehiculo = Vehiculo::query()->where('identificador', 'VHC-001')->sole();
    expect($vehiculo->base_id)->toBeNull()
        ->and($vehiculo->estado)->toBe('activo');
});

it('rechaza un estado fuera del enum sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    $this->post(route('panel.vehiculos.store'), payloadVehiculo(['estado' => 'volando']))
        ->assertSessionHasErrors('estado');

    expect(Vehiculo::query()->where('identificador', 'VHC-001')->exists())->toBeFalse();
});

it('rechaza una base_id inexistente sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    $this->post(route('panel.vehiculos.store'), payloadVehiculo(['base_id' => '999999']))
        ->assertSessionHasErrors('base_id');

    expect(Vehiculo::query()->where('identificador', 'VHC-001')->exists())->toBeFalse();
});

it('el identificador duplicado entre vehículos activos es un error de validación, no un QueryException', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    Vehiculo::query()->create(['identificador' => 'VHC-001', 'estado' => 'activo']);

    $this->post(route('panel.vehiculos.store'), payloadVehiculo(['estado' => 'taller']))
        ->assertSessionHasErrors('identificador');

    expect(Vehiculo::query()->where('estado', 'taller')->exists())->toBeFalse();
});

it('un vehículo dado de baja no bloquea el re-alta con el mismo identificador', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    $existente = Vehiculo::query()->create(['identificador' => 'VHC-001', 'estado' => 'activo']);
    $existente->delete();

    $this->post(route('panel.vehiculos.store'), payloadVehiculo())
        ->assertRedirect(route('panel.vehiculos.index'));

    expect(Vehiculo::query()->where('identificador', 'VHC-001')->count())->toBe(1);
});

it('edita un vehículo existente, incluida su asignación de base', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    $baseVieja = PerBase::query()->create(['nombre' => 'Base Norte']);
    $baseNueva = PerBase::query()->create(['nombre' => 'Base Sur']);

    $vehiculo = Vehiculo::query()->create(['identificador' => 'VHC-001', 'base_id' => $baseVieja->id, 'estado' => 'activo']);

    $this->put(
        route('panel.vehiculos.update', $vehiculo),
        payloadVehiculo(['identificador' => 'VHC-001-B', 'base_id' => (string) $baseNueva->id, 'estado' => 'de_baja']),
    )->assertRedirect(route('panel.vehiculos.index'));

    $vehiculo->refresh();
    expect($vehiculo->identificador)->toBe('VHC-001-B')
        ->and($vehiculo->base_id)->toBe($baseNueva->id)
        ->and($vehiculo->estado)->toBe('de_baja');
});

it('da de alta un vehículo con la ficha completa de inventario', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    $this->post(route('panel.vehiculos.store'), payloadVehiculo([
        'marca' => 'Toyota',
        'modelo' => 'Hilux',
        'anio' => '2022',
        'combustible' => 'diesel',
        'es_4x4' => '1',
        'kilometraje_inicial' => '1000.50',
        'kilometraje_actual' => '1500.75',
    ]))->assertRedirect(route('panel.vehiculos.index'));

    $vehiculo = Vehiculo::query()->where('identificador', 'VHC-001')->sole();

    expect($vehiculo->marca)->toBe('Toyota')
        ->and($vehiculo->modelo)->toBe('Hilux')
        ->and($vehiculo->anio)->toBe(2022)
        ->and($vehiculo->combustible)->toBe('diesel')
        ->and($vehiculo->es_4x4)->toBeTrue()
        ->and((string) $vehiculo->kilometraje_inicial)->toBe('1000.50')
        ->and((string) $vehiculo->kilometraje_actual)->toBe('1500.75');
});

it('edita la ficha completa de un vehículo existente, incluido el kilometraje inicial', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    $vehiculo = Vehiculo::query()->create([
        'identificador' => 'VHC-001',
        'estado' => 'activo',
        'kilometraje_inicial' => '1000.00',
        'kilometraje_actual' => '1000.00',
    ]);

    $this->put(
        route('panel.vehiculos.update', $vehiculo),
        payloadVehiculo([
            'marca' => 'Ford',
            'modelo' => 'Ranger',
            'anio' => '2020',
            'combustible' => 'gasolina',
            'es_4x4' => '1',
            'kilometraje_inicial' => '500.00',
            'kilometraje_actual' => '2000.00',
        ]),
    )->assertRedirect(route('panel.vehiculos.index'));

    $vehiculo->refresh();
    expect($vehiculo->marca)->toBe('Ford')
        ->and($vehiculo->modelo)->toBe('Ranger')
        ->and($vehiculo->anio)->toBe(2020)
        ->and($vehiculo->combustible)->toBe('gasolina')
        ->and($vehiculo->es_4x4)->toBeTrue()
        ->and((string) $vehiculo->kilometraje_inicial)->toBe('500.00')
        ->and((string) $vehiculo->kilometraje_actual)->toBe('2000.00');
});

it('rechaza un combustible fuera del catálogo sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    $this->post(route('panel.vehiculos.store'), payloadVehiculo(['combustible' => 'nafta']))
        ->assertSessionHasErrors('combustible');

    expect(Vehiculo::query()->where('identificador', 'VHC-001')->exists())->toBeFalse();
});

it('acepta el estado pausa tanto al alta como a la edición, sin romper activo/taller/de_baja', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    $this->post(route('panel.vehiculos.store'), payloadVehiculo(['estado' => 'pausa']))
        ->assertRedirect(route('panel.vehiculos.index'));

    $vehiculo = Vehiculo::query()->where('identificador', 'VHC-001')->sole();
    expect($vehiculo->estado)->toBe('pausa');

    $this->put(
        route('panel.vehiculos.update', $vehiculo),
        payloadVehiculo(['estado' => 'activo']),
    )->assertRedirect(route('panel.vehiculos.index'));

    expect($vehiculo->refresh()->estado)->toBe('activo');

    $this->put(
        route('panel.vehiculos.update', $vehiculo),
        payloadVehiculo(['estado' => 'taller']),
    )->assertRedirect(route('panel.vehiculos.index'));

    expect($vehiculo->refresh()->estado)->toBe('taller');

    $this->put(
        route('panel.vehiculos.update', $vehiculo),
        payloadVehiculo(['estado' => 'de_baja']),
    )->assertRedirect(route('panel.vehiculos.index'));

    expect($vehiculo->refresh()->estado)->toBe('de_baja');

    $this->put(
        route('panel.vehiculos.update', $vehiculo),
        payloadVehiculo(['estado' => 'pausa']),
    )->assertRedirect(route('panel.vehiculos.index'));

    expect($vehiculo->refresh()->estado)->toBe('pausa');
});

it('filtra el listado por base y por estado', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    $baseNorte = PerBase::query()->create(['nombre' => 'Base Norte']);
    $baseSur = PerBase::query()->create(['nombre' => 'Base Sur']);

    Vehiculo::query()->create(['identificador' => 'VHC-NORTE', 'base_id' => $baseNorte->id, 'estado' => 'activo']);
    Vehiculo::query()->create(['identificador' => 'VHC-SUR', 'base_id' => $baseSur->id, 'estado' => 'taller']);

    $this->get(route('panel.vehiculos.index', ['base_id' => $baseNorte->id]))
        ->assertOk()
        ->assertSee('VHC-NORTE')
        ->assertDontSee('VHC-SUR');

    $this->get(route('panel.vehiculos.index', ['estado' => 'taller']))
        ->assertOk()
        ->assertSee('VHC-SUR')
        ->assertDontSee('VHC-NORTE');
});

it('registra en bitácora el alta, la edición y la baja de un vehículo', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    $this->post(route('panel.vehiculos.store'), payloadVehiculo());

    $vehiculo = Vehiculo::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'man_vehiculos')
        ->where('registro_id', $vehiculo->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['identificador'])->toBe('VHC-001');

    $this->put(
        route('panel.vehiculos.update', $vehiculo),
        payloadVehiculo(['identificador' => 'VHC-001-B']),
    )->assertRedirect(route('panel.vehiculos.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'man_vehiculos')
        ->where('registro_id', $vehiculo->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['identificador'])->toBe('VHC-001-B');

    $this->delete(route('panel.vehiculos.destroy', $vehiculo))
        ->assertRedirect(route('panel.vehiculos.index'));

    Bitacora::query()
        ->where('tabla', 'man_vehiculos')
        ->where('registro_id', $vehiculo->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja un vehículo por soft delete: no aparece en el índice y un segundo intento da 404', function () {
    [$encargado, $idRol] = usuarioConRolParaVehiculos('encargado', 'encargado_operaciones');
    entrarAlPanelParaVehiculos($encargado, $idRol);

    $this->post(route('panel.vehiculos.store'), payloadVehiculo());
    $vehiculo = Vehiculo::query()->sole();

    $this->delete(route('panel.vehiculos.destroy', $vehiculo))
        ->assertRedirect(route('panel.vehiculos.index'));

    $borrado = Vehiculo::withTrashed()->findOrFail($vehiculo->id);
    expect($borrado->trashed())->toBeTrue();

    $this->get(route('panel.vehiculos.index'))
        ->assertOk()
        ->assertDontSee('VHC-001');

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.vehiculos.destroy', $vehiculo))->assertNotFound();
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaVehiculos('piloto.curioso', 'piloto');
    entrarAlPanelParaVehiculos($piloto, $idRol);

    $vehiculo = Vehiculo::query()->create(['identificador' => 'VHC-EXISTENTE', 'estado' => 'activo']);

    $this->get(route('panel.vehiculos.index'))->assertForbidden();
    $this->get(route('panel.vehiculos.create'))->assertForbidden();
    $this->post(route('panel.vehiculos.store'), payloadVehiculo())->assertForbidden();
    $this->get(route('panel.vehiculos.edit', $vehiculo))->assertForbidden();
    $this->put(route('panel.vehiculos.update', $vehiculo), payloadVehiculo())->assertForbidden();
    $this->delete(route('panel.vehiculos.destroy', $vehiculo))->assertForbidden();

    expect(Vehiculo::query()->count())->toBe(1)
        ->and($vehiculo->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    [$multirol, $idEncargado] = usuarioConRolParaVehiculos('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaVehiculos($multirol, $idPiloto);
    $this->post(route('panel.vehiculos.store'), payloadVehiculo())->assertForbidden();
    expect(Vehiculo::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaVehiculos($multirol, $idEncargado);
    $this->post(route('panel.vehiculos.store'), payloadVehiculo())->assertRedirect();
    expect(Vehiculo::query()->count())->toBe(1);
});

it('publica el ítem de menú de vehículos gateado por mantenimiento.vehiculo.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.recursos.items.vehiculos')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'mantenimiento.vehiculo.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.vehiculos.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
