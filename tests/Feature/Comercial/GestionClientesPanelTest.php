<?php

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
 * HU-22 (tarea 33): alta y mantenimiento de clientes con sus contactos —
 * primer ABM completo del panel. Permisos evaluados contra el ROL ACTIVO de
 * la sesión, nunca la unión de los roles del usuario (invariante 10 de
 * CLAUDE.md). Mismo patrón de asserts que
 * tests/Feature/Seguridad/RevocarDispositivoPanelTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaClientes(string $username, string $rol): array
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
function entrarAlPanelParaClientes(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de alta/edición: razón social + un contacto. */
function payloadCliente(array $overrides = []): array
{
    return array_merge([
        'razon_social' => 'Agropecuaria del Valle S.R.L.',
        'nit' => '123456789',
        'tipo_persona' => 'juridica',
        'contactos' => [
            ['tipo' => 'dueno', 'nombre' => 'Juana Pérez', 'telefono' => '77712345', 'email' => 'juana@example.com'],
        ],
    ], $overrides);
}

it('da de alta un cliente con al menos un contacto', function () {
    [$encargado, $idRol] = usuarioConRolParaClientes('encargado', 'encargado_operaciones');
    entrarAlPanelParaClientes($encargado, $idRol);

    $this->post(route('panel.clientes.store'), payloadCliente())
        ->assertRedirect(route('panel.clientes.index'));

    $cliente = Cliente::query()->where('razon_social', 'Agropecuaria del Valle S.R.L.')->sole();

    expect($cliente->nit)->toBe('123456789')
        ->and($cliente->contactos()->count())->toBe(1)
        ->and($cliente->contactos()->first()->nombre)->toBe('Juana Pérez');
});

it('registra en bitácora el alta, la edición y la baja de un cliente', function () {
    [$encargado, $idRol] = usuarioConRolParaClientes('encargado', 'encargado_operaciones');
    entrarAlPanelParaClientes($encargado, $idRol);

    $this->post(route('panel.clientes.store'), payloadCliente());

    $cliente = Cliente::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'com_clientes')
        ->where('registro_id', $cliente->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['razon_social'])->toBe('Agropecuaria del Valle S.R.L.');

    $this->put(
        route('panel.clientes.update', $cliente),
        payloadCliente(['razon_social' => 'Agropecuaria del Valle S.R.L. (renombrada)']),
    )->assertRedirect(route('panel.clientes.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'com_clientes')
        ->where('registro_id', $cliente->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['razon_social'])->toBe('Agropecuaria del Valle S.R.L. (renombrada)');

    $this->delete(route('panel.clientes.destroy', $cliente))
        ->assertRedirect(route('panel.clientes.index'));

    Bitacora::query()
        ->where('tabla', 'com_clientes')
        ->where('registro_id', $cliente->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja un cliente por soft delete: no aparece en el índice y un segundo intento da 404', function () {
    [$encargado, $idRol] = usuarioConRolParaClientes('encargado', 'encargado_operaciones');
    entrarAlPanelParaClientes($encargado, $idRol);

    $this->post(route('panel.clientes.store'), payloadCliente());
    $cliente = Cliente::query()->sole();

    $this->delete(route('panel.clientes.destroy', $cliente))
        ->assertRedirect(route('panel.clientes.index'));

    $borrado = Cliente::withTrashed()->findOrFail($cliente->id);
    expect($borrado->trashed())->toBeTrue();

    $this->get(route('panel.clientes.index'))
        ->assertOk()
        ->assertDontSee('Agropecuaria del Valle S.R.L.');

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.clientes.destroy', $cliente))->assertNotFound();
});

it('el NIT duplicado entre clientes activos es un error de validación, no un QueryException', function () {
    [$encargado, $idRol] = usuarioConRolParaClientes('encargado', 'encargado_operaciones');
    entrarAlPanelParaClientes($encargado, $idRol);

    Cliente::query()->create(['razon_social' => 'Cliente Uno S.R.L.', 'nit' => '111222333', 'tipo_persona' => 'juridica']);

    $this->post(route('panel.clientes.store'), payloadCliente([
        'razon_social' => 'Cliente Dos S.R.L.',
        'nit' => '111222333',
    ]))->assertSessionHasErrors('nit');

    expect(Cliente::query()->where('razon_social', 'Cliente Dos S.R.L.')->exists())->toBeFalse();
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaClientes('piloto.curioso', 'piloto');
    entrarAlPanelParaClientes($piloto, $idRol);

    $cliente = Cliente::query()->create(['razon_social' => 'Cliente Existente S.R.L.', 'tipo_persona' => 'juridica']);

    $this->get(route('panel.clientes.index'))->assertForbidden();
    $this->get(route('panel.clientes.create'))->assertForbidden();
    $this->post(route('panel.clientes.store'), payloadCliente())->assertForbidden();
    $this->get(route('panel.clientes.edit', $cliente))->assertForbidden();
    $this->put(route('panel.clientes.update', $cliente), payloadCliente())->assertForbidden();
    $this->delete(route('panel.clientes.destroy', $cliente))->assertForbidden();

    expect(Cliente::query()->count())->toBe(1)
        ->and($cliente->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    [$multirol, $idEncargado] = usuarioConRolParaClientes('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaClientes($multirol, $idPiloto);
    $this->post(route('panel.clientes.store'), payloadCliente())->assertForbidden();
    expect(Cliente::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaClientes($multirol, $idEncargado);
    $this->post(route('panel.clientes.store'), payloadCliente())->assertRedirect();
    expect(Cliente::query()->count())->toBe(1);
});

it('publica el ítem de menú de clientes gateado por comercial.cliente.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.comercial.items.clientes')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'comercial.cliente.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.clientes.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
