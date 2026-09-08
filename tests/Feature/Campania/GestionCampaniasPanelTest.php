<?php

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
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
 * ADR 0015 punto 1 (tarea 69): ABM de campañas — mismo molde que
 * tests/Feature/Comercial/GestionContratosPanelTest.php, con una máquina de
 * estados más chica (dos transiciones, no cuatro). Permisos evaluados contra
 * el ROL ACTIVO de la sesión (invariante 10 de CLAUDE.md).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaCampanias(string $username, string $rol): array
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
function entrarAlPanelParaCampanias(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de alta/edición. */
function payloadCampania(array $overrides = []): array
{
    return array_merge([
        'codigo' => '2025-2026',
        'nombre' => 'Campaña 2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
    ], $overrides);
}

it('da de alta una campaña en estado planificada', function () {
    [$encargado, $idRol] = usuarioConRolParaCampanias('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampanias($encargado, $idRol);

    $this->post(route('panel.campanias.store'), payloadCampania())
        ->assertRedirect(route('panel.campanias.index'));

    $campania = Campania::query()->where('codigo', '2025-2026')->sole();

    expect($campania->estado)->toBe(EstadoCampania::Planificada)
        ->and($campania->nombre)->toBe('Campaña 2025-2026');
});

it('rechaza un código de campaña duplicado entre filas activas', function () {
    [$encargado, $idRol] = usuarioConRolParaCampanias('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampanias($encargado, $idRol);

    $this->post(route('panel.campanias.store'), payloadCampania())
        ->assertRedirect(route('panel.campanias.index'));

    $this->post(route('panel.campanias.store'), payloadCampania(['nombre' => 'Otra']))
        ->assertSessionHasErrors('codigo');

    expect(Campania::query()->where('codigo', '2025-2026')->count())->toBe(1);
});

it('el dueño abre una campaña planificada', function () {
    [$dueno, $idRol] = usuarioConRolParaCampanias('dueno', 'dueno');
    entrarAlPanelParaCampanias($dueno, $idRol);

    $campania = Campania::query()->create([
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);

    $this->post(route('panel.campanias.cambiar-estado', $campania), ['estado' => 'abierta'])
        ->assertRedirect(route('panel.campanias.index'));

    expect($campania->fresh()->estado)->toBe(EstadoCampania::Abierta);
});

it('rechaza abrir una campaña cuyo rango se solapa con otra ya abierta', function () {
    [$dueno, $idRol] = usuarioConRolParaCampanias('dueno', 'dueno');
    entrarAlPanelParaCampanias($dueno, $idRol);

    Campania::query()->create([
        'codigo' => '2024-2025',
        'fecha_inicio' => '2024-07-01',
        'fecha_fin' => '2025-06-30',
        'estado' => 'abierta',
    ]);

    $campania = Campania::query()->create([
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-06-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);

    $this->post(route('panel.campanias.cambiar-estado', $campania), ['estado' => 'abierta'])
        ->assertRedirect(route('panel.campanias.index'))
        ->assertSessionHasErrors('estado');

    expect($campania->fresh()->estado)->toBe(EstadoCampania::Planificada);
});

it('la transición cerrada a abierta es rechazada por la máquina de estados y no llega a persistir', function () {
    [$dueno, $idRol] = usuarioConRolParaCampanias('dueno', 'dueno');
    entrarAlPanelParaCampanias($dueno, $idRol);

    $campania = Campania::query()->create([
        'codigo' => '2024-2025',
        'fecha_inicio' => '2024-07-01',
        'fecha_fin' => '2025-06-30',
        'estado' => 'cerrada',
    ]);

    $this->post(route('panel.campanias.cambiar-estado', $campania), ['estado' => 'abierta'])
        ->assertSessionHasErrors('estado');

    expect($campania->fresh()->estado)->toBe(EstadoCampania::Cerrada);
});

it('un encargado no puede cambiar el estado de una campaña, solo el dueño', function () {
    [$encargado, $idRol] = usuarioConRolParaCampanias('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampanias($encargado, $idRol);

    $campania = Campania::query()->create([
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);

    $this->post(route('panel.campanias.cambiar-estado', $campania), ['estado' => 'abierta'])
        ->assertForbidden();

    expect($campania->fresh()->estado)->toBe(EstadoCampania::Planificada);
});

it('registra en bitácora el alta y el cambio de estado de una campaña', function () {
    [$encargado, $idRolEncargado] = usuarioConRolParaCampanias('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampanias($encargado, $idRolEncargado);

    $this->post(route('panel.campanias.store'), payloadCampania());

    $campania = Campania::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'cpn_campanias')
        ->where('registro_id', $campania->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id);

    [$dueno, $idRolDueno] = usuarioConRolParaCampanias('dueno', 'dueno');
    entrarAlPanelParaCampanias($dueno, $idRolDueno);

    $this->post(route('panel.campanias.cambiar-estado', $campania), ['estado' => 'abierta'])
        ->assertRedirect(route('panel.campanias.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'cpn_campanias')
        ->where('registro_id', $campania->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['estado'])->toBe('abierta')
        ->and($filaActualizado->user_id)->toBe($dueno->id);
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaCampanias('piloto.curioso', 'piloto');
    entrarAlPanelParaCampanias($piloto, $idRol);

    $campania = Campania::query()->create([
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);

    $this->get(route('panel.campanias.index'))->assertForbidden();
    $this->get(route('panel.campanias.create'))->assertForbidden();
    $this->post(route('panel.campanias.store'), payloadCampania(['codigo' => '2026-2027']))->assertForbidden();
    $this->get(route('panel.campanias.edit', $campania))->assertForbidden();
    $this->put(route('panel.campanias.update', $campania), payloadCampania())->assertForbidden();
    $this->post(route('panel.campanias.cambiar-estado', $campania), ['estado' => 'abierta'])->assertForbidden();

    expect(Campania::query()->count())->toBe(1)
        ->and($campania->fresh()->estado)->toBe(EstadoCampania::Planificada);
});

it('renderiza el listado y los formularios de alta/edición', function () {
    [$encargado, $idRol] = usuarioConRolParaCampanias('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampanias($encargado, $idRol);

    $campania = Campania::query()->create([
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);

    $this->get(route('panel.campanias.index'))->assertOk()->assertSee('2025-2026');
    $this->get(route('panel.campanias.create'))->assertOk();
    $this->get(route('panel.campanias.edit', $campania))->assertOk()->assertSee('2025-2026');
});

it('el listado ofrece "Abrir" a una planificada y "Cerrar" a una abierta, solo al dueño', function () {
    [$dueno, $idRol] = usuarioConRolParaCampanias('dueno', 'dueno');
    entrarAlPanelParaCampanias($dueno, $idRol);

    Campania::query()->create([
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);
    Campania::query()->create([
        'codigo' => '2024-2025',
        'fecha_inicio' => '2024-07-01',
        'fecha_fin' => '2025-06-30',
        'estado' => 'abierta',
    ]);
    Campania::query()->create([
        'codigo' => '2023-2024',
        'fecha_inicio' => '2023-07-01',
        'fecha_fin' => '2024-06-30',
        'estado' => 'cerrada',
    ]);

    $this->get(route('panel.campanias.index'))
        ->assertOk()
        ->assertSee(__('campania.campanias.accion_abrir'))
        ->assertSee(__('campania.campanias.accion_cerrar'));
});

it('publica el ítem de menú de campañas gateado por campania.campania.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.seguridad.items.campanias')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'campania.campania.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.campanias.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
