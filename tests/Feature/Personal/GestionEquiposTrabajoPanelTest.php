<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoIntegrante;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * Tarea 72 (HU-49, ADR 0015 punto 3): ABM de equipos de trabajo — el piloto y
 * su auxiliar, con el equipamiento asignado, cada uno con su propia
 * vigencia. Permisos evaluados contra el ROL ACTIVO de la sesión (invariante
 * 10 de CLAUDE.md). Mismo patrón de asserts que
 * tests/Feature/Mantenimiento/GestionGeneradoresPanelTest.php (tarea 72,
 * etapa 1), sumando los 4 tests de aceptación de la HU que dependen de los
 * casos de uso de integrantes/recursos: aviso de solapamiento entre equipos
 * (se guarda igual, ADR 0015 punto 3), rechazo dentro del mismo equipo,
 * ficha vigente a una fecha pasada, y recurso inexistente rechazado en el
 * caso de uso.
 *
 * El quinto criterio de aceptación del prompt original ("un equipo de una
 * campaña cerrada no acepta integrantes nuevos") no se implementa: desde la
 * corrección del ADR 0015 del 8/9/2026, `per_equipos_trabajo` NO lleva
 * `campania_id` — el equipo es de Agrocom y trabaja para varias campañas de
 * clientes distintos a la vez, así que no existe una "campaña del equipo"
 * contra la que cerrar nada. Esa guarda, si el negocio la quiere, vive del
 * lado del gasto/estadía (tarea 73/74), que sí eligen una campaña explícita
 * — no de `EquipoIntegrante`. Queda anotado en runs/72.md.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaEquipos(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaEquipos(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function payloadEquipoTrabajo(int $baseId, array $overrides = []): array
{
    return array_merge([
        'codigo' => 'EQ-001',
        'nombre' => '',
        'base_id' => (string) $baseId,
        'estado' => 'activo',
        'desde' => '2026-01-01',
        'hasta' => '',
    ], $overrides);
}

function personaDePruebaEquipos(string $nombre): PerPersona
{
    return PerPersona::query()->create([
        'nombre' => $nombre,
        'rol' => RolOperativoPersona::Piloto,
        'activo' => true,
    ]);
}

it('da de alta un equipo de trabajo con código, base, estado y vigencia válidos', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);

    $this->post(route('panel.equipos-trabajo.store'), payloadEquipoTrabajo($base->id, [
        'nombre' => 'Cuadrilla 1',
        'estado' => 'activo',
        'desde' => '2026-01-01',
        'hasta' => '',
    ]))->assertRedirect(route('panel.equipos-trabajo.index'));

    $equipo = EquipoTrabajo::query()->where('codigo', 'EQ-001')->sole();

    expect($equipo->nombre)->toBe('Cuadrilla 1')
        ->and($equipo->base_id)->toBe($base->id)
        ->and($equipo->estado->value)->toBe('activo')
        ->and($equipo->desde->toDateString())->toBe('2026-01-01')
        ->and($equipo->hasta)->toBeNull();
});

it('rechaza un base_id inexistente sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $this->post(route('panel.equipos-trabajo.store'), payloadEquipoTrabajo(999999))
        ->assertSessionHasErrors('base_id');

    expect(EquipoTrabajo::query()->where('codigo', 'EQ-001')->exists())->toBeFalse();
});

it('rechaza una fecha hasta anterior a desde sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);

    $this->post(route('panel.equipos-trabajo.store'), payloadEquipoTrabajo($base->id, [
        'desde' => '2026-03-01',
        'hasta' => '2026-02-01',
    ]))->assertSessionHasErrors('hasta');

    expect(EquipoTrabajo::query()->where('codigo', 'EQ-001')->exists())->toBeFalse();
});

it('el código duplicado entre equipos activos es un error de validación, no un QueryException', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    EquipoTrabajo::query()->create(['codigo' => 'EQ-001', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-01-01']);

    $this->post(route('panel.equipos-trabajo.store'), payloadEquipoTrabajo($base->id))
        ->assertSessionHasErrors('codigo');

    expect(EquipoTrabajo::query()->where('codigo', 'EQ-001')->count())->toBe(1);
});

it('un equipo dado de baja no bloquea el re-alta con el mismo código', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    $existente = EquipoTrabajo::query()->create(['codigo' => 'EQ-001', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-01-01']);
    $existente->delete();

    $this->post(route('panel.equipos-trabajo.store'), payloadEquipoTrabajo($base->id))
        ->assertRedirect(route('panel.equipos-trabajo.index'));

    expect(EquipoTrabajo::query()->where('codigo', 'EQ-001')->count())->toBe(1);
});

it('edita un equipo de trabajo existente, incluida su base y vigencia', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $baseVieja = PerBase::query()->create(['nombre' => 'Base Norte']);
    $baseNueva = PerBase::query()->create(['nombre' => 'Base Sur']);

    $equipo = EquipoTrabajo::query()->create(['codigo' => 'EQ-001', 'base_id' => $baseVieja->id, 'estado' => 'activo', 'desde' => '2026-01-01']);

    $this->put(
        route('panel.equipos-trabajo.update', $equipo),
        payloadEquipoTrabajo($baseNueva->id, ['codigo' => 'EQ-001-B', 'estado' => 'inactivo', 'hasta' => '2026-06-30']),
    )->assertRedirect(route('panel.equipos-trabajo.index'));

    $equipo->refresh();
    expect($equipo->codigo)->toBe('EQ-001-B')
        ->and($equipo->base_id)->toBe($baseNueva->id)
        ->and($equipo->estado->value)->toBe('inactivo')
        ->and($equipo->hasta->toDateString())->toBe('2026-06-30');
});

it('filtra el listado por base y por estado', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $baseNorte = PerBase::query()->create(['nombre' => 'Base Norte']);
    $baseSur = PerBase::query()->create(['nombre' => 'Base Sur']);

    EquipoTrabajo::query()->create(['codigo' => 'EQ-NORTE', 'base_id' => $baseNorte->id, 'estado' => 'activo', 'desde' => '2026-01-01']);
    EquipoTrabajo::query()->create(['codigo' => 'EQ-SUR', 'base_id' => $baseSur->id, 'estado' => 'inactivo', 'desde' => '2026-01-01']);

    $this->get(route('panel.equipos-trabajo.index', ['base_id' => $baseNorte->id]))
        ->assertOk()
        ->assertSee('EQ-NORTE')
        ->assertDontSee('EQ-SUR');

    $this->get(route('panel.equipos-trabajo.index', ['estado' => 'inactivo']))
        ->assertOk()
        ->assertSee('EQ-SUR')
        ->assertDontSee('EQ-NORTE');
});

it('da de baja un equipo de trabajo por soft delete: no aparece en el índice y un segundo intento da 404', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    $this->post(route('panel.equipos-trabajo.store'), payloadEquipoTrabajo($base->id));
    $equipo = EquipoTrabajo::query()->sole();

    $this->delete(route('panel.equipos-trabajo.destroy', $equipo))
        ->assertRedirect(route('panel.equipos-trabajo.index'));

    $borrado = EquipoTrabajo::withTrashed()->findOrFail($equipo->id);
    expect($borrado->trashed())->toBeTrue();

    $this->get(route('panel.equipos-trabajo.index'))
        ->assertOk()
        ->assertDontSee('EQ-001');

    $this->delete(route('panel.equipos-trabajo.destroy', $equipo))->assertNotFound();
});

it('registra en bitácora el alta, la edición y la baja de un equipo de trabajo', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    $this->post(route('panel.equipos-trabajo.store'), payloadEquipoTrabajo($base->id));

    $equipo = EquipoTrabajo::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'per_equipos_trabajo')
        ->where('registro_id', $equipo->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['codigo'])->toBe('EQ-001');

    $this->put(
        route('panel.equipos-trabajo.update', $equipo),
        payloadEquipoTrabajo($base->id, ['codigo' => 'EQ-001-B']),
    )->assertRedirect(route('panel.equipos-trabajo.index'));

    Bitacora::query()
        ->where('tabla', 'per_equipos_trabajo')
        ->where('registro_id', $equipo->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    $this->delete(route('panel.equipos-trabajo.destroy', $equipo))
        ->assertRedirect(route('panel.equipos-trabajo.index'));

    Bitacora::query()
        ->where('tabla', 'per_equipos_trabajo')
        ->where('registro_id', $equipo->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaEquipos('piloto.curioso', 'piloto');
    entrarAlPanelParaEquipos($piloto, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    $equipo = EquipoTrabajo::query()->create(['codigo' => 'EQ-EXISTENTE', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-01-01']);

    $this->get(route('panel.equipos-trabajo.index'))->assertForbidden();
    $this->get(route('panel.equipos-trabajo.create'))->assertForbidden();
    $this->post(route('panel.equipos-trabajo.store'), payloadEquipoTrabajo($base->id))->assertForbidden();
    $this->get(route('panel.equipos-trabajo.show', $equipo))->assertForbidden();
    $this->get(route('panel.equipos-trabajo.edit', $equipo))->assertForbidden();
    $this->put(route('panel.equipos-trabajo.update', $equipo), payloadEquipoTrabajo($base->id))->assertForbidden();
    $this->delete(route('panel.equipos-trabajo.destroy', $equipo))->assertForbidden();

    expect(EquipoTrabajo::query()->count())->toBe(1)
        ->and($equipo->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    [$multirol, $idEncargado] = usuarioConRolParaEquipos('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);

    entrarAlPanelParaEquipos($multirol, $idPiloto);
    $this->post(route('panel.equipos-trabajo.store'), payloadEquipoTrabajo($base->id))->assertForbidden();
    expect(EquipoTrabajo::query()->count())->toBe(0);

    entrarAlPanelParaEquipos($multirol, $idEncargado);
    $this->post(route('panel.equipos-trabajo.store'), payloadEquipoTrabajo($base->id))->assertRedirect();
    expect(EquipoTrabajo::query()->count())->toBe(1);
});

it('publica el ítem de menú de equipos de trabajo gateado por personal.equipo_trabajo.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.recursos.items.equipos_trabajo')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'personal.equipo_trabajo.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.equipos-trabajo.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});

// ── Criterios de aceptación de la HU-49 que dependen de los casos de uso ──

it('asignar una persona a un segundo equipo con vigencia solapada se guarda y avisa', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    $equipoA = EquipoTrabajo::query()->create(['codigo' => 'EQ-A', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-01-01']);
    $equipoB = EquipoTrabajo::query()->create(['codigo' => 'EQ-B', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-01-01']);
    $persona = personaDePruebaEquipos('Piloto Prestado');

    EquipoIntegrante::query()->create([
        'equipo_trabajo_id' => $equipoA->id,
        'persona_id' => $persona->id,
        'rol_equipo' => 'piloto',
        'desde' => '2026-03-01',
        'hasta' => null,
    ]);

    $respuesta = $this->post(route('panel.equipos-trabajo.integrantes.store', $equipoB), [
        'persona_id' => $persona->id,
        'rol_equipo' => 'auxiliar',
        'desde' => '2026-03-10',
        'hasta' => null,
    ]);

    $respuesta->assertRedirect();
    expect(session('aviso'))->toContain('EQ-A');

    expect(EquipoIntegrante::query()->where('persona_id', $persona->id)->count())->toBe(2)
        ->and(EquipoIntegrante::query()->where('equipo_trabajo_id', $equipoB->id)->where('persona_id', $persona->id)->exists())->toBeTrue();
});

it('duplicar la misma persona en el mismo equipo con vigencias que se pisan se rechaza', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    $equipo = EquipoTrabajo::query()->create(['codigo' => 'EQ-A', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-01-01']);
    $persona = personaDePruebaEquipos('Piloto Duplicado');

    EquipoIntegrante::query()->create([
        'equipo_trabajo_id' => $equipo->id,
        'persona_id' => $persona->id,
        'rol_equipo' => 'piloto',
        'desde' => '2026-03-01',
        'hasta' => null,
    ]);

    $this->post(route('panel.equipos-trabajo.integrantes.store', $equipo), [
        'persona_id' => $persona->id,
        'rol_equipo' => 'auxiliar',
        'desde' => '2026-03-15',
        'hasta' => null,
    ])->assertSessionHasErrors('persona_id');

    expect(EquipoIntegrante::query()->where('equipo_trabajo_id', $equipo->id)->where('persona_id', $persona->id)->count())->toBe(1);
});

it('la ficha del equipo consultada al 14/3 devuelve al auxiliar que estaba ese día, no al que lo reemplazó después', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    $equipo = EquipoTrabajo::query()->create(['codigo' => 'EQ-A', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-01-01']);

    $auxiliarViejo = personaDePruebaEquipos('Auxiliar Marzo');
    $auxiliarNuevo = personaDePruebaEquipos('Auxiliar Reemplazo');

    EquipoIntegrante::query()->create([
        'equipo_trabajo_id' => $equipo->id,
        'persona_id' => $auxiliarViejo->id,
        'rol_equipo' => 'auxiliar',
        'desde' => '2026-02-01',
        'hasta' => '2026-03-31',
    ]);

    EquipoIntegrante::query()->create([
        'equipo_trabajo_id' => $equipo->id,
        'persona_id' => $auxiliarNuevo->id,
        'rol_equipo' => 'auxiliar',
        'desde' => '2026-04-01',
        'hasta' => null,
    ]);

    // `assertSee`/`assertDontSee` sobre la página entera no alcanza: el
    // formulario de alta de integrante lista TODAS las personas activas en
    // su <select>, sin importar la fecha consultada — "Auxiliar Reemplazo"
    // aparece siempre ahí, aun cuando no integre el equipo esa fecha. Lo que
    // hay que verificar es la FILA de la lista de vigentes, que Blade
    // renderiza como `<span>{nombre}</span>` sin atributos (a diferencia del
    // `<option value="…">{nombre}</option>` del select) — ver
    // `equipos-trabajo/show.blade.php`.
    $html14Marzo = $this->get(route('panel.equipos-trabajo.show', ['equipoTrabajo' => $equipo, 'fecha' => '2026-03-14']))
        ->assertOk()
        ->getContent();

    expect($html14Marzo)->toContain('<span>Auxiliar Marzo</span>')
        ->not->toContain('<span>Auxiliar Reemplazo</span>');

    $html14Abril = $this->get(route('panel.equipos-trabajo.show', ['equipoTrabajo' => $equipo, 'fecha' => '2026-04-14']))
        ->assertOk()
        ->getContent();

    expect($html14Abril)->toContain('<span>Auxiliar Reemplazo</span>')
        ->not->toContain('<span>Auxiliar Marzo</span>');
});

it('asignar un recurso_tipo vehiculo con un recurso_id que no existe en man_vehiculos se rechaza en el caso de uso', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    $equipo = EquipoTrabajo::query()->create(['codigo' => 'EQ-A', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-01-01']);

    $this->post(route('panel.equipos-trabajo.recursos.store', $equipo), [
        'recurso_tipo' => 'vehiculo',
        'recurso_id' => 999999,
        'desde' => '2026-03-01',
        'hasta' => null,
    ])->assertSessionHasErrors('recurso_id');

    expect(EquipoRecurso::query()->where('equipo_trabajo_id', $equipo->id)->exists())->toBeFalse();
});

it('asigna un vehículo activo existente a un equipo correctamente', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    $equipo = EquipoTrabajo::query()->create(['codigo' => 'EQ-A', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-01-01']);

    $vehiculoId = DB::table('man_vehiculos')->insertGetId([
        'identificador' => 'ABC-123',
        'estado' => 'activo',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->post(route('panel.equipos-trabajo.recursos.store', $equipo), [
        'recurso_tipo' => 'vehiculo',
        'recurso_id' => $vehiculoId,
        'desde' => '2026-03-01',
        'hasta' => null,
    ])->assertRedirect();

    expect(EquipoRecurso::query()->where('equipo_trabajo_id', $equipo->id)->where('recurso_id', $vehiculoId)->exists())->toBeTrue();
});

it('finaliza la vigencia de un integrante sin borrar la fila', function () {
    [$encargado, $idRol] = usuarioConRolParaEquipos('encargado', 'encargado_operaciones');
    entrarAlPanelParaEquipos($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);
    $equipo = EquipoTrabajo::query()->create(['codigo' => 'EQ-A', 'base_id' => $base->id, 'estado' => 'activo', 'desde' => '2026-01-01']);
    $persona = personaDePruebaEquipos('Piloto Finaliza');

    $integrante = EquipoIntegrante::query()->create([
        'equipo_trabajo_id' => $equipo->id,
        'persona_id' => $persona->id,
        'rol_equipo' => 'piloto',
        'desde' => '2026-01-01',
        'hasta' => null,
    ]);

    $this->delete(route('panel.equipos-trabajo.integrantes.destroy', [$equipo, $integrante]), [
        'hasta' => '2026-06-30',
    ])->assertRedirect();

    $integrante->refresh();
    expect($integrante->trashed())->toBeFalse()
        ->and($integrante->hasta->toDateString())->toBe('2026-06-30');
});
