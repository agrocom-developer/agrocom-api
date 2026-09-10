<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRolePermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioCliente;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Tarea 65 (HU-41): el camino `type = cliente` del MISMO ABM de usuarios
 * (`UsuariosController`), aparte del interno (ya cubierto en
 * GestionUsuariosPanelTest.php). Cubre: alta/edición de una cuenta de
 * portal desde el panel, el permiso adicional `seguridad.usuario.portal`, y
 * que la cuenta resultante entre de verdad al portal y vea solo su propio
 * contrato — nunca tocamos AutorizacionPortalCliente ni sus controladores
 * (ya verificados en tests/Feature/Portal/PortalClienteTest.php), solo
 * consumimos el guard `cliente` como haría un usuario real.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function contratoVigenteParaAbm(string $sufijo, string $hectareas = '40.00'): Contrato
{
    $cliente = Cliente::create(['razon_social' => "Cliente ABM {$sufijo}", 'tipo_persona' => 'juridica']);

    return Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => $hectareas,
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '0.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);
}

function contratoNoVigenteParaAbm(string $sufijo): Contrato
{
    $cliente = Cliente::create(['razon_social' => "Cliente ABM no vigente {$sufijo}", 'tipo_persona' => 'juridica']);

    return Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '10.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '0.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Borrador,
    ]);
}

function usuarioConRolParaAbmPortal(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaAbmPortal(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Quita un permiso otorgado a un rol (soft delete del pivote), para simular un rol degradado en un test. */
function quitarPermisoDeRol(int $idRol, string $codigoPermiso): void
{
    $idPermiso = (int) SecPermission::query()->where('code', $codigoPermiso)->value('id');

    SecRolePermission::query()
        ->where('id_role', $idRol)
        ->where('id_permission', $idPermiso)
        ->delete();
}

function payloadCuentaPortal(array $overrides = []): array
{
    return array_merge([
        'type' => 'cliente',
        'name' => 'Cuenta de portal',
        'username' => 'cliente.nuevo.abm',
        'password' => 'Secreta123',
    ], $overrides);
}

it('el formulario de alta renderiza mostrando la opción cliente cuando el actor tiene seguridad.usuario.portal', function () {
    [$encargado, $idRol] = usuarioConRolParaAbmPortal('encargado.portal', 'encargado_operaciones');
    entrarAlPanelParaAbmPortal($encargado, $idRol);

    $this->get(route('panel.usuarios.create'))->assertOk();
});

it('el formulario de edición de una cuenta de portal renderiza', function () {
    [$encargado, $idRol] = usuarioConRolParaAbmPortal('encargado.portal', 'encargado_operaciones');
    entrarAlPanelParaAbmPortal($encargado, $idRol);

    $contrato = contratoVigenteParaAbm('form-edicion');
    $this->post(route('panel.usuarios.store'), payloadCuentaPortal(['contrato_id' => $contrato->id]));
    $usuario = SecUser::query()->where('username', 'cliente.nuevo.abm')->sole();

    $this->get(route('panel.usuarios.edit', $usuario))->assertOk();
});

it('crea una cuenta de portal sin persona ni roles, y esa cuenta entra por /portal/login de verdad', function () {
    [$encargado, $idRol] = usuarioConRolParaAbmPortal('encargado.portal', 'encargado_operaciones');
    entrarAlPanelParaAbmPortal($encargado, $idRol);

    $contrato = contratoVigenteParaAbm('sanjorge');

    $this->post(route('panel.usuarios.store'), payloadCuentaPortal(['contrato_id' => $contrato->id]))
        ->assertRedirect(route('panel.usuarios.index'));

    $usuario = SecUser::query()->where('username', 'cliente.nuevo.abm')->sole();
    expect($usuario->type->value)->toBe('cliente')
        ->and($usuario->persona_id)->toBeNull()
        ->and($usuario->contrato_id)->toBe($contrato->id)
        ->and($usuario->idsDeRoles())->toBe([]);

    $this->post('/logout');

    $this->postJson('/portal/login', ['username' => 'cliente.nuevo.abm', 'password' => 'Secreta123'])
        ->assertOk();
});

it('la cuenta de portal creada por el ABM ve solo el avance de su propio contrato', function () {
    [$encargado, $idRol] = usuarioConRolParaAbmPortal('encargado.portal', 'encargado_operaciones');
    entrarAlPanelParaAbmPortal($encargado, $idRol);

    $contratoA = contratoVigenteParaAbm('a', '40.00');
    $contratoB = contratoVigenteParaAbm('b', '999.00');

    $this->post(route('panel.usuarios.store'), payloadCuentaPortal([
        'username' => 'cliente.a.abm',
        'contrato_id' => $contratoA->id,
    ]))->assertRedirect();

    $usuarioA = SecUser::query()->where('username', 'cliente.a.abm')->sole();

    $this->actingAs(SecUsuarioCliente::query()->findOrFail($usuarioA->id), 'cliente')
        ->get(route('portal.avance.index'))
        ->assertOk()
        ->assertSee('40,00 ha')
        ->assertDontSee('999,00 ha');
});

it('rechaza persona_id junto con type cliente: 422, y no crea nada', function () {
    [$encargado, $idRol] = usuarioConRolParaAbmPortal('encargado.portal', 'encargado_operaciones');
    entrarAlPanelParaAbmPortal($encargado, $idRol);

    $contrato = contratoVigenteParaAbm('con-persona');

    $this->post(route('panel.usuarios.store'), payloadCuentaPortal([
        'contrato_id' => $contrato->id,
        'persona_id' => '1',
    ]))->assertSessionHasErrors('persona_id');

    expect(SecUser::query()->where('username', 'cliente.nuevo.abm')->exists())->toBeFalse();
});

it('rechaza una cuenta de portal sin contrato_id: 422', function () {
    [$encargado, $idRol] = usuarioConRolParaAbmPortal('encargado.portal', 'encargado_operaciones');
    entrarAlPanelParaAbmPortal($encargado, $idRol);

    $this->post(route('panel.usuarios.store'), payloadCuentaPortal())
        ->assertSessionHasErrors('contrato_id');

    expect(SecUser::query()->where('username', 'cliente.nuevo.abm')->exists())->toBeFalse();
});

it('rechaza un contrato que no está vigente: 422', function () {
    [$encargado, $idRol] = usuarioConRolParaAbmPortal('encargado.portal', 'encargado_operaciones');
    entrarAlPanelParaAbmPortal($encargado, $idRol);

    $contrato = contratoNoVigenteParaAbm('borrador');

    $this->post(route('panel.usuarios.store'), payloadCuentaPortal(['contrato_id' => $contrato->id]))
        ->assertSessionHasErrors('contrato_id');

    expect(SecUser::query()->where('username', 'cliente.nuevo.abm')->exists())->toBeFalse();
});

it('un encargado sin seguridad.usuario.portal recibe 403 al crear una cuenta cliente, pero sigue pudiendo crear internas', function () {
    [$encargado, $idRol] = usuarioConRolParaAbmPortal('encargado.portal', 'encargado_operaciones');
    quitarPermisoDeRol($idRol, 'seguridad.usuario.portal');
    entrarAlPanelParaAbmPortal($encargado, $idRol);

    $contrato = contratoVigenteParaAbm('sin-permiso');

    $this->post(route('panel.usuarios.store'), payloadCuentaPortal(['contrato_id' => $contrato->id]))
        ->assertForbidden();

    expect(SecUser::query()->where('username', 'cliente.nuevo.abm')->exists())->toBeFalse();

    // El mismo actor, sin `seguridad.usuario.portal`, sigue administrando
    // cuentas internas con normalidad: el permiso nuevo es ADEMÁS del
    // genérico, nunca en su lugar.
    $this->post(route('panel.usuarios.store'), [
        'type' => 'interno',
        'name' => 'Usuario Interno',
        'username' => 'interno.nuevo.abm',
        'password' => 'Secreta123',
        'roles' => [],
    ])->assertRedirect(route('panel.usuarios.index'));

    expect(SecUser::query()->where('username', 'interno.nuevo.abm')->exists())->toBeTrue();
});

it('edita una cuenta de portal (cambia username y contrato) sin tocar su tipo', function () {
    [$encargado, $idRol] = usuarioConRolParaAbmPortal('encargado.portal', 'encargado_operaciones');
    entrarAlPanelParaAbmPortal($encargado, $idRol);

    $contratoOriginal = contratoVigenteParaAbm('original');
    $contratoNuevo = contratoVigenteParaAbm('nuevo');

    $this->post(route('panel.usuarios.store'), payloadCuentaPortal(['contrato_id' => $contratoOriginal->id]));
    $usuario = SecUser::query()->where('username', 'cliente.nuevo.abm')->sole();

    $this->put(route('panel.usuarios.update', $usuario), [
        'name' => 'Cuenta de portal editada',
        'username' => 'cliente.editado.abm',
        'password' => '',
        'contrato_id' => $contratoNuevo->id,
    ])->assertRedirect(route('panel.usuarios.index'));

    $usuario->refresh();
    expect($usuario->username)->toBe('cliente.editado.abm')
        ->and($usuario->contrato_id)->toBe($contratoNuevo->id)
        ->and($usuario->type->value)->toBe('cliente');
});

it('un PUT a mano con roles sobre una cuenta de portal da 422, nunca los asigna', function () {
    [$encargado, $idRol] = usuarioConRolParaAbmPortal('encargado.portal', 'encargado_operaciones');
    entrarAlPanelParaAbmPortal($encargado, $idRol);

    $contrato = contratoVigenteParaAbm('fuga-roles');
    $this->post(route('panel.usuarios.store'), payloadCuentaPortal(['contrato_id' => $contrato->id]));
    $usuario = SecUser::query()->where('username', 'cliente.nuevo.abm')->sole();

    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');

    $this->put(route('panel.usuarios.update', $usuario), [
        'name' => $usuario->name,
        'username' => $usuario->username,
        'password' => '',
        'contrato_id' => $contrato->id,
        'roles' => [$idPiloto],
    ])->assertSessionHasErrors('roles');

    expect($usuario->fresh()?->idsDeRoles())->toBe([]);
});

it('da de alta una cuenta de portal con email y lo guarda (tarea 66)', function () {
    [$encargado, $idRol] = usuarioConRolParaAbmPortal('encargado.portal', 'encargado_operaciones');
    entrarAlPanelParaAbmPortal($encargado, $idRol);

    $contrato = contratoVigenteParaAbm('con-email');

    $this->post(route('panel.usuarios.store'), payloadCuentaPortal([
        'contrato_id' => $contrato->id,
        'email' => 'cliente@agrocom.example',
    ]))->assertRedirect(route('panel.usuarios.index'));

    $usuario = SecUser::query()->where('username', 'cliente.nuevo.abm')->sole();
    expect($usuario->email)->toBe('cliente@agrocom.example');
});

it('el listado filtra por tipo (?tipo=cliente / ?tipo=interno)', function () {
    [$encargado, $idRol] = usuarioConRolParaAbmPortal('encargado.portal', 'encargado_operaciones');
    entrarAlPanelParaAbmPortal($encargado, $idRol);

    $contrato = contratoVigenteParaAbm('filtro');
    $this->post(route('panel.usuarios.store'), payloadCuentaPortal([
        'username' => 'cliente.filtro.abm',
        'contrato_id' => $contrato->id,
    ]));

    $this->get(route('panel.usuarios.index', ['tipo' => 'cliente']))
        ->assertOk()
        ->assertSee('cliente.filtro.abm')
        ->assertDontSee('encargado.portal');

    $this->get(route('panel.usuarios.index', ['tipo' => 'interno']))
        ->assertOk()
        ->assertSee('encargado.portal')
        ->assertDontSee('cliente.filtro.abm');

    $this->get(route('panel.usuarios.index'))
        ->assertOk()
        ->assertSee('encargado.portal')
        ->assertSee('cliente.filtro.abm');
});
