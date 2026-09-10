<?php

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
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
 * HU-35 (tarea 49): "como encargado, quiero registrar el combustible del
 * generador y de los vehículos, para imputarlo a la campaña"
 * (plan_sprints.md Sprint 10 §220). Reescrito por la tarea 73 (HU-50): la
 * carga se imputa al equipo de trabajo y al recurso concreto que la
 * consumió — "así sabemos qué vehículo solicitó nuevo combustible" — y el
 * recurso elegido tiene que haber estado asignado a ESE equipo en la fecha
 * de la carga.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaCombustible(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaCombustible(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function encargadoEntraAlPanelParaCombustible(): SecUser
{
    [$encargado, $idRol] = usuarioConRolParaCombustible('encargado.combustible', 'encargado_operaciones');
    entrarAlPanelParaCombustible($encargado, $idRol);

    return $encargado;
}

function baseParaCombustible(): PerBase
{
    return PerBase::create(['nombre' => 'Base de combustible '.uniqid()]);
}

function equipoParaCombustible(PerBase $base, string $desde = '2026-01-01', ?string $hasta = null): EquipoTrabajo
{
    return EquipoTrabajo::create([
        'codigo' => 'EQ-COMB-'.uniqid(),
        'base_id' => $base->id,
        'estado' => 'activo',
        'desde' => $desde,
        'hasta' => $hasta,
    ]);
}

/** Asigna un vehículo al equipo con la vigencia dada y devuelve [modelo, clave compuesta "vehiculo:{id}"]. */
function asignarVehiculoAEquipo(EquipoTrabajo $equipo, string $desde = '2026-01-01', ?string $hasta = null): array
{
    $vehiculo = Vehiculo::create(['identificador' => 'VEH-'.uniqid(), 'estado' => 'activo']);

    EquipoRecurso::create([
        'equipo_trabajo_id' => $equipo->id,
        'recurso_tipo' => 'vehiculo',
        'recurso_id' => $vehiculo->id,
        'desde' => $desde,
        'hasta' => $hasta,
    ]);

    return [$vehiculo, "vehiculo:{$vehiculo->id}"];
}

/** Asigna un generador al equipo con la vigencia dada y devuelve [modelo, clave compuesta "generador:{id}"]. */
function asignarGeneradorAEquipo(EquipoTrabajo $equipo, string $desde = '2026-01-01', ?string $hasta = null): array
{
    $generador = Generador::create(['identificador' => 'GEN-'.uniqid(), 'estado' => 'activo']);

    EquipoRecurso::create([
        'equipo_trabajo_id' => $equipo->id,
        'recurso_tipo' => 'generador',
        'recurso_id' => $generador->id,
        'desde' => $desde,
        'hasta' => $hasta,
    ]);

    return [$generador, "generador:{$generador->id}"];
}

/** Payload mínimo válido de alta. */
function payloadCombustible(int $baseId, int $equipoTrabajoId, string $recurso, array $overrides = []): array
{
    return array_merge([
        'fecha' => '2026-09-20',
        'base_id' => $baseId,
        'equipo_trabajo_id' => $equipoTrabajoId,
        'recurso' => $recurso,
        'litros' => '50.00',
        'monto' => '350.00',
    ], $overrides);
}

it('registra una carga de combustible con un vehículo y otra con un generador, ambos asignados al equipo', function () {
    $base = baseParaCombustible();
    $equipo = equipoParaCombustible($base);
    [$vehiculo, $claveVehiculo] = asignarVehiculoAEquipo($equipo);
    [$generador, $claveGenerador] = asignarGeneradorAEquipo($equipo);
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $claveVehiculo))
        ->assertRedirect(route('panel.combustible.index'));

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $claveGenerador))
        ->assertRedirect(route('panel.combustible.index'));

    expect(Combustible::query()->count())->toBe(2);

    $deVehiculo = Combustible::query()->where('recurso_tipo', 'vehiculo')->sole();
    $deGenerador = Combustible::query()->where('recurso_tipo', 'generador')->sole();

    expect($deVehiculo->litros)->toBe('50.00')
        ->and($deVehiculo->monto)->toBe('350.00')
        ->and($deVehiculo->recurso_id)->toBe($vehiculo->id)
        ->and($deVehiculo->equipo_trabajo_id)->toBe($equipo->id)
        ->and($deGenerador->recurso_tipo)->toBe('generador')
        ->and($deGenerador->recurso_id)->toBe($generador->id);
});

it('rechaza un valor de recurso mal formado con un error de validación, nunca un 500', function () {
    $base = baseParaCombustible();
    $equipo = equipoParaCombustible($base);
    asignarVehiculoAEquipo($equipo);
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, 'camioneta:1'))
        ->assertRedirect()
        ->assertSessionHasErrors('recurso');

    expect(Combustible::query()->count())->toBe(0);
});

it('rechaza litros o monto no positivos', function () {
    $base = baseParaCombustible();
    $equipo = equipoParaCombustible($base);
    [, $clave] = asignarVehiculoAEquipo($equipo);
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $clave, ['litros' => '0']))
        ->assertRedirect()
        ->assertSessionHasErrors('litros');

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $clave, ['litros' => '-5']))
        ->assertRedirect()
        ->assertSessionHasErrors('litros');

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $clave, ['monto' => '0']))
        ->assertRedirect()
        ->assertSessionHasErrors('monto');

    expect(Combustible::query()->count())->toBe(0);
});

it('rechaza un recurso que no estaba asignado a ese equipo, con mensaje traducido', function () {
    $base = baseParaCombustible();
    $equipo = equipoParaCombustible($base);
    $otroEquipo = equipoParaCombustible($base);
    [, $claveDeOtroEquipo] = asignarVehiculoAEquipo($otroEquipo);
    encargadoEntraAlPanelParaCombustible();

    $respuesta = $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $claveDeOtroEquipo));

    $respuesta->assertRedirect()->assertSessionHasErrors('recurso');
    expect(session('errors')->first('recurso'))->not->toBeEmpty()
        ->and(Combustible::query()->count())->toBe(0);
});

it('rechaza un recurso fuera de su vigencia en el equipo (fecha anterior a "desde" o posterior a "hasta")', function () {
    $base = baseParaCombustible();
    $equipo = equipoParaCombustible($base);
    [, $claveVigenciaAcotada] = asignarVehiculoAEquipo($equipo, '2026-06-01', '2026-06-30');
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $claveVigenciaAcotada, ['fecha' => '2026-05-15']))
        ->assertRedirect()
        ->assertSessionHasErrors('recurso');

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $claveVigenciaAcotada, ['fecha' => '2026-07-01']))
        ->assertRedirect()
        ->assertSessionHasErrors('recurso');

    expect(Combustible::query()->count())->toBe(0);
});

it('acepta el mismo recurso con una fecha dentro de su vigencia en el equipo', function () {
    $base = baseParaCombustible();
    $equipo = equipoParaCombustible($base);
    [, $clave] = asignarVehiculoAEquipo($equipo, '2026-06-01', '2026-06-30');
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $clave, ['fecha' => '2026-06-15']))
        ->assertRedirect(route('panel.combustible.index'));

    expect(Combustible::query()->count())->toBe(1);
});

it('guarda una carga sin campania_id: es un consumo interno que no pertenece a ninguna campaña', function () {
    $base = baseParaCombustible();
    $equipo = equipoParaCombustible($base);
    [, $clave] = asignarVehiculoAEquipo($equipo);
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $clave))
        ->assertRedirect(route('panel.combustible.index'));

    expect(Combustible::query()->sole()->campania_id)->toBeNull();
});

it('registra una carga atribuida a la campaña donde se consumió', function () {
    $base = baseParaCombustible();
    $equipo = equipoParaCombustible($base);
    [, $clave] = asignarVehiculoAEquipo($equipo);
    $cliente = Cliente::create(['razon_social' => 'Cliente de combustible '.uniqid(), 'tipo_persona' => 'juridica']);
    $campania = Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'abierta',
    ]);
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $clave, ['campania_id' => $campania->id]))
        ->assertRedirect(route('panel.combustible.index'));

    expect(Combustible::query()->sole()->campania_id)->toBe($campania->id);
});

it('rechaza una carga contra una campaña cerrada', function () {
    $base = baseParaCombustible();
    $equipo = equipoParaCombustible($base);
    [, $clave] = asignarVehiculoAEquipo($equipo);
    $cliente = Cliente::create(['razon_social' => 'Cliente de combustible '.uniqid(), 'tipo_persona' => 'juridica']);
    $campaniaCerrada = Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => '2024-2025',
        'fecha_inicio' => '2024-07-01',
        'fecha_fin' => '2025-06-30',
        'estado' => 'cerrada',
    ]);
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $clave, ['campania_id' => $campaniaCerrada->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('campania_id');

    expect(Combustible::query()->count())->toBe(0);
});

it('filtra el listado por base y por rango de fecha', function () {
    $baseUno = baseParaCombustible();
    $baseDos = baseParaCombustible();
    $equipoUno = equipoParaCombustible($baseUno);
    $equipoDos = equipoParaCombustible($baseDos);
    [, $claveUno] = asignarVehiculoAEquipo($equipoUno);
    [, $claveDos] = asignarVehiculoAEquipo($equipoDos);
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($baseUno->id, $equipoUno->id, $claveUno, ['fecha' => '2026-09-05']));
    $this->post(route('panel.combustible.store'), payloadCombustible($baseUno->id, $equipoUno->id, $claveUno, ['fecha' => '2026-09-25']));
    $this->post(route('panel.combustible.store'), payloadCombustible($baseDos->id, $equipoDos->id, $claveDos, ['fecha' => '2026-09-10']));

    expect(Combustible::query()->count())->toBe(3);

    $porBase = $this->get(route('panel.combustible.index', ['base_id' => $baseUno->id]));
    $porBase->assertOk();
    expect($porBase->viewData('combustibles')->total())->toBe(2);

    $porRango = $this->get(route('panel.combustible.index', ['desde' => '2026-09-08', 'hasta' => '2026-09-30']));
    $porRango->assertOk();
    expect($porRango->viewData('combustibles')->total())->toBe(2);

    $porBaseYRango = $this->get(route('panel.combustible.index', [
        'base_id' => $baseUno->id,
        'desde' => '2026-09-08',
        'hasta' => '2026-09-30',
    ]));
    $porBaseYRango->assertOk();
    expect($porBaseYRango->viewData('combustibles')->total())->toBe(1);
});

it('filtra el listado por equipo y muestra el total exacto de ese equipo, en decimales de string', function () {
    $base = baseParaCombustible();
    $equipoUno = equipoParaCombustible($base);
    $equipoDos = equipoParaCombustible($base);
    [, $claveUno] = asignarVehiculoAEquipo($equipoUno);
    [, $claveDos] = asignarVehiculoAEquipo($equipoDos);
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipoUno->id, $claveUno, ['monto' => '0.10']));
    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipoUno->id, $claveUno, ['monto' => '0.20']));
    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipoDos->id, $claveDos, ['monto' => '999.99']));

    $respuesta = $this->get(route('panel.combustible.index', ['equipo_trabajo_id' => $equipoUno->id]));
    $respuesta->assertOk();

    expect($respuesta->viewData('combustibles')->total())->toBe(2)
        ->and($respuesta->viewData('total'))->toBe('0.30');
});

it('un rol sin los permisos correspondientes recibe 403 en todas las acciones', function () {
    $base = baseParaCombustible();
    $equipo = equipoParaCombustible($base);
    [, $clave] = asignarVehiculoAEquipo($equipo);

    [$curioso, $idRol] = usuarioConRolParaCombustible('piloto.curioso.combustible', 'piloto');
    entrarAlPanelParaCombustible($curioso, $idRol);

    $combustible = Combustible::query()->create([
        'fecha' => '2026-09-20',
        'base_id' => $base->id,
        'equipo_trabajo_id' => $equipo->id,
        'recurso_tipo' => 'vehiculo',
        'recurso_id' => (int) explode(':', $clave)[1],
        'litros' => '50.00',
        'monto' => '350.00',
    ]);

    $this->get(route('panel.combustible.index'))->assertForbidden();
    $this->get(route('panel.combustible.create'))->assertForbidden();
    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $clave))->assertForbidden();
    $this->delete(route('panel.combustible.destroy', $combustible))->assertForbidden();

    expect(Combustible::query()->count())->toBe(1)
        ->and($combustible->fresh()?->trashed())->toBeFalse();
});

it('registra en bitácora el alta y la baja de una carga de combustible', function () {
    $base = baseParaCombustible();
    $equipo = equipoParaCombustible($base);
    [, $clave] = asignarVehiculoAEquipo($equipo);
    $encargado = encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $clave));
    $combustible = Combustible::query()->sole();

    $filaCreada = Bitacora::query()
        ->where('tabla', 'fin_combustibles')
        ->where('registro_id', $combustible->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreada->user_id)->toBe($encargado->id)
        ->and($filaCreada->despues['monto'])->toBe('350.00');

    $this->delete(route('panel.combustible.destroy', $combustible))
        ->assertRedirect(route('panel.combustible.index'));

    Bitacora::query()
        ->where('tabla', 'fin_combustibles')
        ->where('registro_id', $combustible->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja una carga por soft delete: no la borra físicamente ni aparece en el índice', function () {
    $base = baseParaCombustible();
    $equipo = equipoParaCombustible($base);
    [, $clave] = asignarVehiculoAEquipo($equipo);
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, $equipo->id, $clave));
    $combustible = Combustible::query()->sole();

    $this->delete(route('panel.combustible.destroy', $combustible))
        ->assertRedirect(route('panel.combustible.index'));

    $borrado = Combustible::withTrashed()->findOrFail($combustible->id);
    expect($borrado->trashed())->toBeTrue()
        ->and(Combustible::query()->count())->toBe(0)
        ->and(Combustible::withTrashed()->count())->toBe(1);

    $this->get(route('panel.combustible.index'))
        ->assertOk()
        ->assertSee(__('finanzas.combustible.vacio'));
});

it('publica el ítem de menú de combustible gateado por finanzas.combustible.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.financiero.items.combustible')->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'finanzas.combustible.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.combustible.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
