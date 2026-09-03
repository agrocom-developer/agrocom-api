<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\PlanMantenimiento;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/*
 * HU-38 (tarea 54): planes de mantenimiento preventivo por horas de vuelo de
 * dron, para que el sistema avise antes de la falla. Cuarto ABM del módulo
 * `Mantenimiento` (ADR 0011, extensión 3/9/2026). Permisos evaluados contra
 * el ROL ACTIVO de la sesión, nunca la unión de los roles del usuario
 * (invariante 10 de CLAUDE.md). Mismo patrón de asserts que
 * tests/Feature/Mantenimiento/GestionBateriasPanelTest.php (tarea 51).
 *
 * `DemoSeeder` solo lo necesitan los tests de alerta por horas de vuelo: la
 * orden vigente sobre el lote 'L-01' es la que le da un `lote_id`/`orden_id`
 * válidos a los trabajos de prueba (`ope_trabajos` exige las FK reales) —
 * mismo dato demo que usa `GestionBateriasPanelTest`.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaPlanes(string $username, string $rol): array
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
function entrarAlPanelParaPlanes(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de alta/edición. */
function payloadPlan(array $overrides = []): array
{
    return array_merge([
        'modelo' => 'DJI Agras T30',
        'tarea' => 'Cambio de hélices',
        'horas_umbral' => '10.00',
    ], $overrides);
}

/**
 * Orden vigente del dato demo (mismo lote 'L-01' que
 * `GestionBateriasPanelTest::ordenVigenteParaBateria()`) — punto de partida
 * para construir la cadena `orden → trabajo → sesión` que exigen las FK
 * reales de `ope_trabajos`/`ope_sesiones`.
 */
function ordenVigenteParaPlan(): OrdenAplicacion
{
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');

    return OrdenAplicacion::query()
        ->where('lote_id', $loteId)
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();
}

function crearTrabajoParaPlan(): Trabajo
{
    $orden = ordenVigenteParaPlan();

    return Trabajo::query()->create([
        'uuid_cliente' => (string) Str::uuid(),
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => $orden->nro_aplicacion,
        'inicio' => now(),
    ]);
}

/**
 * Sesión CERRADA (`fin` no nulo) de un dron, con `inicio`/`fin` conocidos
 * para que la suma de horas sea exacta y verificable — directo por Eloquent
 * (no por el motor de sync: eso ya lo cubren los tests de TE-05/HU-07), solo
 * hace falta la fila para probar `LecturaHorasVueloPorModeloEloquent`.
 */
function crearSesionCerrada(Trabajo $trabajo, int $secuencia, int $dronId, string $inicio, string $fin): Sesion
{
    $piloto = PerPersona::query()->create(['nombre' => "Piloto Plan {$secuencia}", 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    return Sesion::query()->create([
        'uuid_cliente' => (string) Str::uuid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => $secuencia,
        'piloto_id' => $piloto->id,
        'dron_id' => $dronId,
        'inicio' => $inicio,
        'fin' => $fin,
    ]);
}

it('da de alta un plan de mantenimiento con modelo, tarea y umbral válidos', function () {
    [$encargado, $idRol] = usuarioConRolParaPlanes('encargado', 'encargado_operaciones');
    entrarAlPanelParaPlanes($encargado, $idRol);

    $this->post(route('panel.planes-mantenimiento.store'), payloadPlan())
        ->assertRedirect(route('panel.planes-mantenimiento.index'));

    $plan = PlanMantenimiento::query()->where('modelo', 'DJI Agras T30')->sole();

    expect($plan->tarea)->toBe('Cambio de hélices')
        ->and((float) $plan->horas_umbral)->toBe(10.0);
});

it('rechaza un horas_umbral menor o igual a cero sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaPlanes('encargado', 'encargado_operaciones');
    entrarAlPanelParaPlanes($encargado, $idRol);

    $this->post(route('panel.planes-mantenimiento.store'), payloadPlan(['horas_umbral' => '0']))
        ->assertSessionHasErrors('horas_umbral');

    expect(PlanMantenimiento::query()->count())->toBe(0);
});

it('rechaza un modelo vacío sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaPlanes('encargado', 'encargado_operaciones');
    entrarAlPanelParaPlanes($encargado, $idRol);

    $this->post(route('panel.planes-mantenimiento.store'), payloadPlan(['modelo' => '']))
        ->assertSessionHasErrors('modelo');

    expect(PlanMantenimiento::query()->count())->toBe(0);
});

it('edita un plan de mantenimiento existente', function () {
    [$encargado, $idRol] = usuarioConRolParaPlanes('encargado', 'encargado_operaciones');
    entrarAlPanelParaPlanes($encargado, $idRol);

    $plan = PlanMantenimiento::query()->create(['modelo' => 'DJI Agras T20', 'tarea' => 'Revisión general', 'horas_umbral' => '20.00']);

    $this->put(
        route('panel.planes-mantenimiento.update', $plan),
        payloadPlan(['modelo' => 'DJI Agras T30', 'tarea' => 'Cambio de hélices', 'horas_umbral' => '15.00']),
    )->assertRedirect(route('panel.planes-mantenimiento.index'));

    $plan->refresh();
    expect($plan->modelo)->toBe('DJI Agras T30')
        ->and($plan->tarea)->toBe('Cambio de hélices')
        ->and((float) $plan->horas_umbral)->toBe(15.0);
});

it('activa la alerta cuando la suma de horas de sesiones cerradas de un dron del modelo alcanza el umbral, no antes', function () {
    $this->seed(DemoSeeder::class);

    [$encargado, $idRol] = usuarioConRolParaPlanes('encargado', 'encargado_operaciones');
    entrarAlPanelParaPlanes($encargado, $idRol);

    PlanMantenimiento::query()->create(['modelo' => 'DJI Agras T30', 'tarea' => 'Cambio de hélices', 'horas_umbral' => '10.00']);

    $dron = Dron::query()->create(['identificador' => 'DRON-ALERTA', 'modelo' => 'DJI Agras T30']);
    $trabajo = crearTrabajoParaPlan();

    // 6h + 4h = 10h exactas: alcanza el umbral (>=), no lo supera de más.
    crearSesionCerrada($trabajo, 1, $dron->id, '2026-01-01 08:00:00', '2026-01-01 14:00:00');

    $this->get(route('panel.planes-mantenimiento.index'))
        ->assertOk()
        ->assertDontSee('warning');

    crearSesionCerrada($trabajo, 2, $dron->id, '2026-01-01 15:00:00', '2026-01-01 19:00:00');

    $this->get(route('panel.planes-mantenimiento.index'))
        ->assertOk()
        ->assertSee('warning');
});

it('no activa la alerta si ningún dron tiene el modelo del plan', function () {
    [$encargado, $idRol] = usuarioConRolParaPlanes('encargado', 'encargado_operaciones');
    entrarAlPanelParaPlanes($encargado, $idRol);

    PlanMantenimiento::query()->create(['modelo' => 'Modelo Sin Drones', 'tarea' => 'Revisión', 'horas_umbral' => '5.00']);

    $this->get(route('panel.planes-mantenimiento.index'))
        ->assertOk()
        ->assertDontSee('warning');
});

it('registra en bitácora el alta, la edición y la baja de un plan de mantenimiento', function () {
    [$encargado, $idRol] = usuarioConRolParaPlanes('encargado', 'encargado_operaciones');
    entrarAlPanelParaPlanes($encargado, $idRol);

    $this->post(route('panel.planes-mantenimiento.store'), payloadPlan());

    $plan = PlanMantenimiento::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'man_planes_mantenimiento')
        ->where('registro_id', $plan->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['modelo'])->toBe('DJI Agras T30');

    $this->put(
        route('panel.planes-mantenimiento.update', $plan),
        payloadPlan(['tarea' => 'Cambio de hélices y revisión de motores']),
    )->assertRedirect(route('panel.planes-mantenimiento.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'man_planes_mantenimiento')
        ->where('registro_id', $plan->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['tarea'])->toBe('Cambio de hélices y revisión de motores');

    $this->delete(route('panel.planes-mantenimiento.destroy', $plan))
        ->assertRedirect(route('panel.planes-mantenimiento.index'));

    Bitacora::query()
        ->where('tabla', 'man_planes_mantenimiento')
        ->where('registro_id', $plan->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja un plan de mantenimiento por soft delete: no aparece en el índice y un segundo intento da 404', function () {
    [$encargado, $idRol] = usuarioConRolParaPlanes('encargado', 'encargado_operaciones');
    entrarAlPanelParaPlanes($encargado, $idRol);

    $this->post(route('panel.planes-mantenimiento.store'), payloadPlan());
    $plan = PlanMantenimiento::query()->sole();

    $this->delete(route('panel.planes-mantenimiento.destroy', $plan))
        ->assertRedirect(route('panel.planes-mantenimiento.index'));

    $borrado = PlanMantenimiento::withTrashed()->findOrFail($plan->id);
    expect($borrado->trashed())->toBeTrue();

    $this->get(route('panel.planes-mantenimiento.index'))
        ->assertOk()
        ->assertDontSee('DJI Agras T30');

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.planes-mantenimiento.destroy', $plan))->assertNotFound();
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaPlanes('piloto.curioso', 'piloto');
    entrarAlPanelParaPlanes($piloto, $idRol);

    $plan = PlanMantenimiento::query()->create(['modelo' => 'DJI Agras T30', 'tarea' => 'Cambio de hélices', 'horas_umbral' => '10.00']);

    $this->get(route('panel.planes-mantenimiento.index'))->assertForbidden();
    $this->get(route('panel.planes-mantenimiento.create'))->assertForbidden();
    $this->post(route('panel.planes-mantenimiento.store'), payloadPlan())->assertForbidden();
    $this->get(route('panel.planes-mantenimiento.edit', $plan))->assertForbidden();
    $this->put(route('panel.planes-mantenimiento.update', $plan), payloadPlan())->assertForbidden();
    $this->delete(route('panel.planes-mantenimiento.destroy', $plan))->assertForbidden();

    expect(PlanMantenimiento::query()->count())->toBe(1)
        ->and($plan->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    [$multirol, $idEncargado] = usuarioConRolParaPlanes('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaPlanes($multirol, $idPiloto);
    $this->post(route('panel.planes-mantenimiento.store'), payloadPlan())->assertForbidden();
    expect(PlanMantenimiento::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaPlanes($multirol, $idEncargado);
    $this->post(route('panel.planes-mantenimiento.store'), payloadPlan())->assertRedirect();
    expect(PlanMantenimiento::query()->count())->toBe(1);
});

it('publica el ítem de menú de planes de mantenimiento gateado por mantenimiento.plan.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.mantenimiento.items.planes')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'mantenimiento.plan.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.planes-mantenimiento.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
