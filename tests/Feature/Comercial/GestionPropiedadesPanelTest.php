<?php

use App\Dominios\Comercial\Aplicacion\ListarPropiedades;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
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
 * ADR 0018: administración de propiedades — nivel de terreno entre `Cliente`
 * y `Campo`. Mismo molde que tests/Feature/Comercial/GestionCamposPanelTest.php
 * (HU-24, tarea 35), sin sub-entidad: los campos de una propiedad se crean y
 * editan desde su propia pantalla (`CamposController`), no acá. Permisos
 * evaluados contra el ROL ACTIVO de la sesión, nunca la unión de los roles
 * del usuario (invariante 10 de CLAUDE.md).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaPropiedades(string $username, string $rol): array
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
function entrarAlPanelParaPropiedades(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function clienteDePropiedadesDePrueba(string $razonSocial = 'Agropecuaria del Valle S.R.L.'): Cliente
{
    return Cliente::query()->create(['razon_social' => $razonSocial, 'tipo_persona' => 'juridica']);
}

/** Payload mínimo válido de alta/edición: cliente + nombre. */
function payloadPropiedad(int $clienteId, array $overrides = []): array
{
    return array_merge([
        'cliente_id' => $clienteId,
        'nombre' => 'Gamelera',
        'ubicacion' => 'Cuatro Cañadas, Santa Cruz, Bolivia',
    ], $overrides);
}

it('da de alta una propiedad', function () {
    $cliente = clienteDePropiedadesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaPropiedades('encargado', 'encargado_operaciones');
    entrarAlPanelParaPropiedades($encargado, $idRol);

    $this->post(route('panel.propiedades.store'), payloadPropiedad($cliente->id))
        ->assertRedirect(route('panel.propiedades.index'));

    $propiedad = Propiedad::query()->where('nombre', 'Gamelera')->sole();

    expect($propiedad->cliente_id)->toBe($cliente->id)
        ->and($propiedad->ubicacion)->toBe('Cuatro Cañadas, Santa Cruz, Bolivia');
});

it('acepta una propiedad sin ubicacion', function () {
    $cliente = clienteDePropiedadesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaPropiedades('encargado', 'encargado_operaciones');
    entrarAlPanelParaPropiedades($encargado, $idRol);

    $this->post(route('panel.propiedades.store'), payloadPropiedad($cliente->id, ['ubicacion' => null]))
        ->assertRedirect(route('panel.propiedades.index'));

    $propiedad = Propiedad::query()->sole();
    expect($propiedad->ubicacion)->toBeNull();
});

it('edita una propiedad, incluida la reasignacion de cliente', function () {
    $clienteOrigen = clienteDePropiedadesDePrueba('Agropecuaria del Valle S.R.L.');
    $clienteDestino = clienteDePropiedadesDePrueba('Estancia Los Robles S.A.');
    [$encargado, $idRol] = usuarioConRolParaPropiedades('encargado', 'encargado_operaciones');
    entrarAlPanelParaPropiedades($encargado, $idRol);

    $this->post(route('panel.propiedades.store'), payloadPropiedad($clienteOrigen->id));
    $propiedad = Propiedad::query()->sole();

    $this->put(route('panel.propiedades.update', $propiedad), payloadPropiedad($clienteDestino->id, [
        'nombre' => 'Gamelera (renombrada)',
    ]))->assertRedirect(route('panel.propiedades.index'));

    $propiedad->refresh();
    expect($propiedad->cliente_id)->toBe($clienteDestino->id)
        ->and($propiedad->nombre)->toBe('Gamelera (renombrada)');
});

it('registra en bitacora el alta, la edicion y la baja de una propiedad', function () {
    $cliente = clienteDePropiedadesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaPropiedades('encargado', 'encargado_operaciones');
    entrarAlPanelParaPropiedades($encargado, $idRol);

    $this->post(route('panel.propiedades.store'), payloadPropiedad($cliente->id));
    $propiedad = Propiedad::query()->sole();

    $filaCreada = Bitacora::query()
        ->where('tabla', 'com_propiedades')
        ->where('registro_id', $propiedad->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreada->user_id)->toBe($encargado->id)
        ->and($filaCreada->despues['nombre'])->toBe('Gamelera');

    $this->put(route('panel.propiedades.update', $propiedad), payloadPropiedad($cliente->id, [
        'nombre' => 'Gamelera (renombrada)',
    ]))->assertRedirect(route('panel.propiedades.index'));

    Bitacora::query()
        ->where('tabla', 'com_propiedades')
        ->where('registro_id', $propiedad->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    $this->delete(route('panel.propiedades.destroy', $propiedad))
        ->assertRedirect(route('panel.propiedades.index'));

    Bitacora::query()
        ->where('tabla', 'com_propiedades')
        ->where('registro_id', $propiedad->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja una propiedad por soft delete: no aparece en el indice y un segundo intento da 404', function () {
    $cliente = clienteDePropiedadesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaPropiedades('encargado', 'encargado_operaciones');
    entrarAlPanelParaPropiedades($encargado, $idRol);

    $this->post(route('panel.propiedades.store'), payloadPropiedad($cliente->id));
    $propiedad = Propiedad::query()->sole();

    $this->delete(route('panel.propiedades.destroy', $propiedad))
        ->assertRedirect(route('panel.propiedades.index'));

    $borrada = Propiedad::withTrashed()->findOrFail($propiedad->id);
    expect($borrada->trashed())->toBeTrue();

    $this->delete(route('panel.propiedades.destroy', $propiedad))->assertNotFound();
});

it('rechaza dar de baja una propiedad con campos asociados', function () {
    $cliente = clienteDePropiedadesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaPropiedades('encargado', 'encargado_operaciones');
    entrarAlPanelParaPropiedades($encargado, $idRol);

    $this->post(route('panel.propiedades.store'), payloadPropiedad($cliente->id));
    $propiedad = Propiedad::query()->sole();

    Campo::query()->create(['propiedad_id' => $propiedad->id, 'nombre' => 'Norte']);

    $this->delete(route('panel.propiedades.destroy', $propiedad))
        ->assertRedirect(route('panel.propiedades.index'))
        ->assertSessionHasErrors('propiedad');

    expect($propiedad->fresh()?->trashed())->toBeFalse();
});

it('el nombre de propiedad duplicado para el mismo cliente es un error de validacion, no un QueryException', function () {
    $cliente = clienteDePropiedadesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaPropiedades('encargado', 'encargado_operaciones');
    entrarAlPanelParaPropiedades($encargado, $idRol);

    $this->post(route('panel.propiedades.store'), payloadPropiedad($cliente->id));

    $this->post(route('panel.propiedades.store'), payloadPropiedad($cliente->id))
        ->assertSessionHasErrors('nombre');

    expect(Propiedad::query()->count())->toBe(1);
});

it('el mismo nombre se permite entre clientes distintos', function () {
    $clienteA = clienteDePropiedadesDePrueba('Agropecuaria del Valle S.R.L.');
    $clienteB = clienteDePropiedadesDePrueba('Estancia Los Robles S.A.');
    [$encargado, $idRol] = usuarioConRolParaPropiedades('encargado', 'encargado_operaciones');
    entrarAlPanelParaPropiedades($encargado, $idRol);

    $this->post(route('panel.propiedades.store'), payloadPropiedad($clienteA->id))
        ->assertRedirect(route('panel.propiedades.index'));
    $this->post(route('panel.propiedades.store'), payloadPropiedad($clienteB->id))
        ->assertRedirect(route('panel.propiedades.index'));

    expect(Propiedad::query()->where('nombre', 'Gamelera')->count())->toBe(2);
});

it('el caso de uso ListarPropiedades filtra por nombre o razon social del cliente', function () {
    // Ejercita la lógica de negocio directo, sin pasar por el Blade del
    // listado (todavía sin construir — ver el reporte final de la tarea):
    // `PropiedadesController::index()` es un adaptador delgado que solo
    // llama a este caso de uso (ADR 0008), así que el filtro se prueba acá.
    $cliente = clienteDePropiedadesDePrueba('Agropecuaria del Valle S.R.L.');
    Propiedad::query()->create(['cliente_id' => $cliente->id, 'nombre' => 'Gamelera']);
    Propiedad::query()->create(['cliente_id' => $cliente->id, 'nombre' => 'Otra propiedad']);

    $porNombre = app(ListarPropiedades::class)->ejecutar('Gamelera');
    expect($porNombre->total())->toBe(1)
        ->and($porNombre->first()->nombre)->toBe('Gamelera');

    $porCliente = app(ListarPropiedades::class)->ejecutar('Agropecuaria');
    expect($porCliente->total())->toBe(2);
});

it('lista propiedades con busqueda por nombre y por cliente', function () {
    // Aduana HTTP de la pantalla: en rojo hasta que exista
    // `propiedades/index.blade.php` (fuera de alcance de esta tarea, la
    // construye el agente de frontend) — la lógica de filtro ya está
    // cubierta sin depender del Blade en la prueba de arriba.
    $cliente = clienteDePropiedadesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaPropiedades('encargado', 'encargado_operaciones');
    entrarAlPanelParaPropiedades($encargado, $idRol);

    Propiedad::query()->create(['cliente_id' => $cliente->id, 'nombre' => 'Gamelera']);
    Propiedad::query()->create(['cliente_id' => $cliente->id, 'nombre' => 'Otra propiedad']);

    $respuesta = $this->get(route('panel.propiedades.index', ['q' => 'Gamelera']))->assertOk();

    $respuesta->assertSee('Gamelera', escape: false);
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    $cliente = clienteDePropiedadesDePrueba();
    [$piloto, $idRol] = usuarioConRolParaPropiedades('piloto.curioso', 'piloto');
    entrarAlPanelParaPropiedades($piloto, $idRol);

    $propiedad = Propiedad::query()->create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad Existente']);

    $this->get(route('panel.propiedades.index'))->assertForbidden();
    $this->get(route('panel.propiedades.create'))->assertForbidden();
    $this->post(route('panel.propiedades.store'), payloadPropiedad($cliente->id))->assertForbidden();
    $this->get(route('panel.propiedades.edit', $propiedad))->assertForbidden();
    $this->put(route('panel.propiedades.update', $propiedad), payloadPropiedad($cliente->id))->assertForbidden();
    $this->delete(route('panel.propiedades.destroy', $propiedad))->assertForbidden();

    expect(Propiedad::query()->count())->toBe(1)
        ->and($propiedad->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    $cliente = clienteDePropiedadesDePrueba();
    [$multirol, $idEncargado] = usuarioConRolParaPropiedades('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaPropiedades($multirol, $idPiloto);
    $this->post(route('panel.propiedades.store'), payloadPropiedad($cliente->id))->assertForbidden();
    expect(Propiedad::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaPropiedades($multirol, $idEncargado);
    $this->post(route('panel.propiedades.store'), payloadPropiedad($cliente->id))->assertRedirect();
    expect(Propiedad::query()->count())->toBe(1);
});

it('publica el item de menu de propiedades gateado por comercial.propiedad.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.comercial.items.propiedades')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'comercial.propiedad.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.propiedades.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
