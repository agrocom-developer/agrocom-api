<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
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
 * (plan_sprints.md Sprint 10 §220). CA esencial: carga por base y fecha;
 * litros y monto en DECIMAL; consultable por período. Cierra Sprint 10.
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

/** Payload mínimo válido de alta. */
function payloadCombustible(int $baseId, array $overrides = []): array
{
    return array_merge([
        'fecha' => '2026-09-20',
        'base_id' => $baseId,
        'destino' => 'generador',
        'litros' => '50.00',
        'monto' => '350.00',
    ], $overrides);
}

it('registra una carga de combustible con destino generador y otra con destino vehiculo', function () {
    $base = baseParaCombustible();
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, ['destino' => 'generador']))
        ->assertRedirect(route('panel.combustible.index'));

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, ['destino' => 'vehiculo']))
        ->assertRedirect(route('panel.combustible.index'));

    expect(Combustible::query()->count())->toBe(2);

    $deGenerador = Combustible::query()->where('destino', 'generador')->sole();
    $deVehiculo = Combustible::query()->where('destino', 'vehiculo')->sole();

    expect($deGenerador->litros)->toBe('50.00')
        ->and($deGenerador->monto)->toBe('350.00')
        ->and($deVehiculo->destino)->toBe('vehiculo');
});

it('rechaza un destino fuera del enum con un error de validación, nunca un 500', function () {
    $base = baseParaCombustible();
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, ['destino' => 'camioneta']))
        ->assertRedirect()
        ->assertSessionHasErrors('destino');

    expect(Combustible::query()->count())->toBe(0);
});

it('rechaza litros o monto no positivos', function () {
    $base = baseParaCombustible();
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, ['litros' => '0']))
        ->assertRedirect()
        ->assertSessionHasErrors('litros');

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, ['litros' => '-5']))
        ->assertRedirect()
        ->assertSessionHasErrors('litros');

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id, ['monto' => '0']))
        ->assertRedirect()
        ->assertSessionHasErrors('monto');

    expect(Combustible::query()->count())->toBe(0);
});

it('filtra el listado por base y por rango de fecha', function () {
    $baseUno = baseParaCombustible();
    $baseDos = baseParaCombustible();
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($baseUno->id, ['fecha' => '2026-09-05']));
    $this->post(route('panel.combustible.store'), payloadCombustible($baseUno->id, ['fecha' => '2026-09-25']));
    $this->post(route('panel.combustible.store'), payloadCombustible($baseDos->id, ['fecha' => '2026-09-10']));

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

it('un rol sin los permisos correspondientes recibe 403 en todas las acciones', function () {
    $base = baseParaCombustible();

    [$curioso, $idRol] = usuarioConRolParaCombustible('piloto.curioso.combustible', 'piloto');
    entrarAlPanelParaCombustible($curioso, $idRol);

    $combustible = Combustible::query()->create([
        'fecha' => '2026-09-20',
        'base_id' => $base->id,
        'destino' => 'generador',
        'litros' => '50.00',
        'monto' => '350.00',
    ]);

    $this->get(route('panel.combustible.index'))->assertForbidden();
    $this->get(route('panel.combustible.create'))->assertForbidden();
    $this->post(route('panel.combustible.store'), payloadCombustible($base->id))->assertForbidden();
    $this->delete(route('panel.combustible.destroy', $combustible))->assertForbidden();

    expect(Combustible::query()->count())->toBe(1)
        ->and($combustible->fresh()?->trashed())->toBeFalse();
});

it('registra en bitácora el alta y la baja de una carga de combustible', function () {
    $base = baseParaCombustible();
    $encargado = encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id));
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
    encargadoEntraAlPanelParaCombustible();

    $this->post(route('panel.combustible.store'), payloadCombustible($base->id));
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
