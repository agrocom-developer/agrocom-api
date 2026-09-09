<?php

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
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
 * ADR 0015 punto 1 (tarea 69, corregido el 8/9/2026): ABM de campañas DEL
 * CLIENTE — mismo molde que tests/Feature/Comercial/GestionContratosPanelTest.php,
 * con una máquina de estados más chica (dos transiciones, no cuatro) y sin
 * guarda de solapamiento: hay tantas campañas abiertas como clientes en
 * campaña, y hasta un mismo cliente puede tener dos a la vez. Permisos
 * evaluados contra el ROL ACTIVO de la sesión (invariante 10 de CLAUDE.md).
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

function clienteParaCampanias(string $razonSocial = 'Agropecuaria del Valle S.R.L.', string $nit = '999888777'): Cliente
{
    return Cliente::query()->create(['razon_social' => $razonSocial, 'nit' => $nit]);
}

/** Payload mínimo válido de alta/edición. */
function payloadCampania(int $clienteId, array $overrides = []): array
{
    return array_merge([
        'cliente_id' => $clienteId,
        'codigo' => '2025-2026',
        'nombre' => 'Campaña 2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
    ], $overrides);
}

it('da de alta una campaña en estado planificada, del cliente elegido', function () {
    $cliente = clienteParaCampanias();
    [$encargado, $idRol] = usuarioConRolParaCampanias('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampanias($encargado, $idRol);

    $this->post(route('panel.campanias.store'), payloadCampania($cliente->id))
        ->assertRedirect(route('panel.campanias.index'));

    $campania = Campania::query()->where('codigo', '2025-2026')->sole();

    expect($campania->estado)->toBe(EstadoCampania::Planificada)
        ->and($campania->cliente_id)->toBe($cliente->id)
        ->and($campania->nombre)->toBe('Campaña 2025-2026');
});

it('rechaza un código de campaña duplicado entre filas activas del mismo cliente', function () {
    $cliente = clienteParaCampanias();
    [$encargado, $idRol] = usuarioConRolParaCampanias('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampanias($encargado, $idRol);

    $this->post(route('panel.campanias.store'), payloadCampania($cliente->id))
        ->assertRedirect(route('panel.campanias.index'));

    $this->post(route('panel.campanias.store'), payloadCampania($cliente->id, ['nombre' => 'Otra']))
        ->assertSessionHasErrors('codigo');

    expect(Campania::query()->where('codigo', '2025-2026')->count())->toBe(1);
});

it('dos clientes distintos pueden tener cada uno una campaña con el mismo código', function () {
    $clienteA = clienteParaCampanias('Agropecuaria del Valle S.R.L.', '999888777');
    $clienteB = clienteParaCampanias('Agrícola San Marcos S.R.L.', '111222333');
    [$encargado, $idRol] = usuarioConRolParaCampanias('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampanias($encargado, $idRol);

    $this->post(route('panel.campanias.store'), payloadCampania($clienteA->id))
        ->assertRedirect(route('panel.campanias.index'));

    $this->post(route('panel.campanias.store'), payloadCampania($clienteB->id))
        ->assertRedirect(route('panel.campanias.index'));

    expect(Campania::query()->where('codigo', '2025-2026')->count())->toBe(2);
});

it('el dueño abre una campaña planificada', function () {
    $cliente = clienteParaCampanias();
    [$dueno, $idRol] = usuarioConRolParaCampanias('dueno', 'dueno');
    entrarAlPanelParaCampanias($dueno, $idRol);

    $campania = Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);

    $this->post(route('panel.campanias.cambiar-estado', $campania), ['estado' => 'abierta'])
        ->assertRedirect(route('panel.campanias.index'));

    expect($campania->fresh()->estado)->toBe(EstadoCampania::Abierta);
});

it('acepta abrir dos campañas del mismo cliente con rangos de fechas solapados', function () {
    $cliente = clienteParaCampanias();
    [$dueno, $idRol] = usuarioConRolParaCampanias('dueno', 'dueno');
    entrarAlPanelParaCampanias($dueno, $idRol);

    Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => 'Soya-verano-2025',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-01-31',
        'estado' => 'abierta',
    ]);

    $maizInvierno = Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => 'Maiz-invierno-2025',
        'fecha_inicio' => '2025-10-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);

    $this->post(route('panel.campanias.cambiar-estado', $maizInvierno), ['estado' => 'abierta'])
        ->assertRedirect(route('panel.campanias.index'))
        ->assertSessionDoesntHaveErrors();

    expect($maizInvierno->fresh()->estado)->toBe(EstadoCampania::Abierta);
});

it('acepta abrir campañas de clientes distintos con rangos de fechas solapados', function () {
    $clienteA = clienteParaCampanias('Agropecuaria del Valle S.R.L.', '999888777');
    $clienteB = clienteParaCampanias('Agrícola San Marcos S.R.L.', '111222333');
    [$dueno, $idRol] = usuarioConRolParaCampanias('dueno', 'dueno');
    entrarAlPanelParaCampanias($dueno, $idRol);

    Campania::query()->create([
        'cliente_id' => $clienteA->id,
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'abierta',
    ]);

    $campaniaB = Campania::query()->create([
        'cliente_id' => $clienteB->id,
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);

    $this->post(route('panel.campanias.cambiar-estado', $campaniaB), ['estado' => 'abierta'])
        ->assertRedirect(route('panel.campanias.index'))
        ->assertSessionDoesntHaveErrors();

    expect($campaniaB->fresh()->estado)->toBe(EstadoCampania::Abierta);
});

it('la transición cerrada a abierta es rechazada por la máquina de estados y no llega a persistir', function () {
    $cliente = clienteParaCampanias();
    [$dueno, $idRol] = usuarioConRolParaCampanias('dueno', 'dueno');
    entrarAlPanelParaCampanias($dueno, $idRol);

    $campania = Campania::query()->create([
        'cliente_id' => $cliente->id,
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
    $cliente = clienteParaCampanias();
    [$encargado, $idRol] = usuarioConRolParaCampanias('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampanias($encargado, $idRol);

    $campania = Campania::query()->create([
        'cliente_id' => $cliente->id,
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
    $cliente = clienteParaCampanias();
    [$encargado, $idRolEncargado] = usuarioConRolParaCampanias('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampanias($encargado, $idRolEncargado);

    $this->post(route('panel.campanias.store'), payloadCampania($cliente->id));

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
    $cliente = clienteParaCampanias();
    [$piloto, $idRol] = usuarioConRolParaCampanias('piloto.curioso', 'piloto');
    entrarAlPanelParaCampanias($piloto, $idRol);

    $campania = Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);

    $this->get(route('panel.campanias.index'))->assertForbidden();
    $this->get(route('panel.campanias.create'))->assertForbidden();
    $this->post(route('panel.campanias.store'), payloadCampania($cliente->id, ['codigo' => '2026-2027']))->assertForbidden();
    $this->get(route('panel.campanias.edit', $campania))->assertForbidden();
    $this->put(route('panel.campanias.update', $campania), payloadCampania($cliente->id))->assertForbidden();
    $this->post(route('panel.campanias.cambiar-estado', $campania), ['estado' => 'abierta'])->assertForbidden();

    expect(Campania::query()->count())->toBe(1)
        ->and($campania->fresh()->estado)->toBe(EstadoCampania::Planificada);
});

it('renderiza el listado y los formularios de alta/edición', function () {
    $cliente = clienteParaCampanias();
    [$encargado, $idRol] = usuarioConRolParaCampanias('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampanias($encargado, $idRol);

    $campania = Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);

    $this->get(route('panel.campanias.index'))->assertOk()->assertSee('2025-2026')->assertSee($cliente->razon_social);
    $this->get(route('panel.campanias.create'))->assertOk()->assertSee($cliente->razon_social);
    $this->get(route('panel.campanias.edit', $campania))->assertOk()->assertSee('2025-2026');
});

it('filtra el listado por cliente', function () {
    $clienteA = clienteParaCampanias('Agropecuaria del Valle S.R.L.', '999888777');
    $clienteB = clienteParaCampanias('Agrícola San Marcos S.R.L.', '111222333');
    [$encargado, $idRol] = usuarioConRolParaCampanias('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampanias($encargado, $idRol);

    Campania::query()->create([
        'cliente_id' => $clienteA->id,
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);
    Campania::query()->create([
        'cliente_id' => $clienteB->id,
        'codigo' => 'Otra-2025',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);

    $respuesta = $this->get(route('panel.campanias.index', ['cliente_id' => $clienteA->id]))->assertOk();

    $respuesta->assertSee('2025-2026')->assertDontSee('Otra-2025');
});

it('el listado ofrece "Abrir" a una planificada y "Cerrar" a una abierta, solo al dueño', function () {
    $cliente = clienteParaCampanias();
    [$dueno, $idRol] = usuarioConRolParaCampanias('dueno', 'dueno');
    entrarAlPanelParaCampanias($dueno, $idRol);

    Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'planificada',
    ]);
    Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => '2024-2025',
        'fecha_inicio' => '2024-07-01',
        'fecha_fin' => '2025-06-30',
        'estado' => 'abierta',
    ]);
    Campania::query()->create([
        'cliente_id' => $cliente->id,
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
        ->where('label', 'menu.comercial.items.campanias')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'campania.campania.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.campanias.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});

// La campaña es del cliente, no de Agrocom (ADR 0015 punto 1, corregido el
// 8/9/2026). El ítem había nacido bajo Seguridad leyéndola como configuración
// de la operación propia; el 9/9 se movió a Comercial. Se fija acá para que no
// vuelva sola: es la única compuerta que separa "información del cliente" de
// "configuración de la casa" en el menú.
it('cuelga el ítem de campañas de Comercial y no de Seguridad', function () {
    $comercial = SecMenu::query()->where('label', 'menu.comercial.label')->sole();

    $itemMenu = SecMenu::query()
        ->where('label', 'menu.comercial.items.campanias')
        ->sole();

    expect($itemMenu->padre_id)->toBe($comercial->id)
        ->and(SecMenu::query()->where('label', 'menu.seguridad.items.campanias')->exists())->toBeFalse();
});
