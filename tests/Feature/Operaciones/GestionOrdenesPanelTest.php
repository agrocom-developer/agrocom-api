<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\TipoAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRolePermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/*
 * HU-25 (tarea 38): órdenes de aplicación desde el panel, con su propia
 * máquina de estados (emitida → vigente). Permisos evaluados contra el ROL
 * ACTIVO de la sesión, nunca la unión de los roles del usuario (invariante
 * 10 de CLAUDE.md). Mismo patrón de asserts que
 * tests/Feature/Comercial/GestionContratosPanelTest.php (tarea 34).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaOrdenes(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

/** Rol nuevo con exactamente los permisos indicados — para aislar `.activar` de `.editar`. */
function rolConPermisosParaOrdenes(string $nombre, array $codigosPermiso): SecRole
{
    $rol = SecRole::query()->create(['name' => $nombre, 'description' => $nombre, 'state' => true]);

    foreach ($codigosPermiso as $codigo) {
        $idPermiso = (int) SecPermission::query()->where('code', $codigo)->value('id');

        (new SecRolePermission(['id_role' => $rol->id, 'id_permission' => $idPermiso]))->save();
    }

    return $rol;
}

/** Entra al panel con un rol activo fijado, como haría el login. */
function entrarAlPanelParaOrdenes(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function clienteParaOrdenes(): Cliente
{
    return Cliente::query()->create(['razon_social' => 'Agropecuaria del Valle S.R.L.', 'nit' => '999888777', 'tipo_persona' => 'juridica']);
}

function contratoParaOrdenes(int $clienteId): Contrato
{
    return Contrato::query()->create([
        'cliente_id' => $clienteId,
        'hectareas_contratadas' => '100.00',
        'aplicaciones_previstas' => 3,
        'precio_ha' => '50.00',
        'monto_total' => '15000.00',
        'fecha_inicio' => Carbon::yesterday()->toDateString(),
        'estado' => EstadoContrato::Vigente,
    ]);
}

function campoParaOrdenes(int $clienteId): Campo
{
    $propiedad = Propiedad::create(['cliente_id' => $clienteId, 'nombre' => 'Propiedad de prueba '.uniqid()]);

    return Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo Norte']);
}

function loteParaOrdenes(Campo $campo, string $codigo = 'L-01'): Lote
{
    return $campo->lotes()->create(['codigo' => $codigo, 'hectareas' => '10.00']);
}

/** Payload mínimo válido de alta/edición. */
function payloadOrden(int $contratoId, int $loteId, array $overrides = []): array
{
    return array_merge([
        'contrato_id' => $contratoId,
        'lote_id' => $loteId,
        'nro_aplicacion' => 1,
        'tipo_aplicacion' => 'desarrollo',
        'litros_ha' => '15.00',
        'fecha_emision' => Carbon::today()->toDateString(),
    ], $overrides);
}

it('da de alta una orden que persiste en estado emitida', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id))
        ->assertRedirect(route('panel.ordenes.index'));

    $orden = OrdenAplicacion::query()->where('contrato_id', $contrato->id)->sole();

    expect($orden->estado)->toBe(EstadoOrdenAplicacion::Emitida)
        ->and($orden->lote_id)->toBe($lote->id);
});

it('el formulario de alta muestra el campo tipo de aplicación, preseleccionado en desarrollo', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);

    $this->get(route('panel.ordenes.create'))
        ->assertOk()
        ->assertSee(__('operaciones.ordenes.campo_tipo_aplicacion'))
        ->assertSee(__('operaciones.tipo_aplicacion.desarrollo'));
});

it('el listado muestra el tipo de aplicación de cada orden y admite filtrarlo', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id, ['tipo_aplicacion' => 'cosecha']));

    $this->get(route('panel.ordenes.index'))
        ->assertOk()
        ->assertSee(__('operaciones.tipo_aplicacion.cosecha'));

    $this->get(route('panel.ordenes.index', ['tipo_aplicacion' => 'siembra']))
        ->assertOk()
        ->assertSee(__('operaciones.ordenes.filtro_vacio'));
});

it('activar una orden emitida la pasa a vigente', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id));
    $orden = OrdenAplicacion::query()->sole();

    $this->post(route('panel.ordenes.activar', $orden))
        ->assertRedirect(route('panel.ordenes.index'));

    expect($orden->fresh()->estado)->toBe(EstadoOrdenAplicacion::Vigente);
});

it('activar una orden que no está emitida es rechazado por la máquina de estados, sin mutar el estado', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $orden = OrdenAplicacion::query()->create([
        ...payloadOrden($contrato->id, $lote->id),
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);

    $this->post(route('panel.ordenes.activar', $orden))
        ->assertRedirect(route('panel.ordenes.index'))
        ->assertSessionHasErrors('estado');

    expect($orden->fresh()->estado)->toBe(EstadoOrdenAplicacion::Vigente);
});

it('una segunda activación sobre el mismo lote falla como error de validación legible, no un QueryException', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $primera = OrdenAplicacion::query()->create([
        ...payloadOrden($contrato->id, $lote->id, ['nro_aplicacion' => 1]),
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $segunda = OrdenAplicacion::query()->create([
        ...payloadOrden($contrato->id, $lote->id, ['nro_aplicacion' => 2]),
        'estado' => EstadoOrdenAplicacion::Emitida,
    ]);

    $this->post(route('panel.ordenes.activar', $segunda))
        ->assertRedirect(route('panel.ordenes.index'))
        ->assertSessionHasErrors('estado');

    expect($segunda->fresh()->estado)->toBe(EstadoOrdenAplicacion::Emitida)
        ->and($primera->fresh()->estado)->toBe(EstadoOrdenAplicacion::Vigente);
});

it('la orden queda visible en el catálogo de sync una vez vigente, no antes', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id));
    $orden = OrdenAplicacion::query()->sole();

    $dispositivo = SecUser::factory()->create();
    $primerPull = $this->actingAs($dispositivo, 'sanctum')->getJson('/api/sync/catalogo')->assertOk();
    expect(collect($primerPull->json('ordenes'))->pluck('id')->all())->not->toContain($orden->id);

    $this->post(route('panel.ordenes.activar', $orden))->assertRedirect(route('panel.ordenes.index'));

    $segundoPull = $this->actingAs($dispositivo, 'sanctum')->getJson('/api/sync/catalogo')->assertOk();
    expect(collect($segundoPull->json('ordenes'))->pluck('id')->all())->toContain($orden->id);
});

it('da de alta una orden de siembra o de cosecha, no solo de desarrollo', function (string $tipo) {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id, ['tipo_aplicacion' => $tipo]))
        ->assertRedirect(route('panel.ordenes.index'));

    $orden = OrdenAplicacion::query()->where('contrato_id', $contrato->id)->sole();

    expect($orden->tipo_aplicacion)->toBe(TipoAplicacion::from($tipo));
})->with(['siembra', 'cosecha']);

it('un tipo_aplicacion fuera del enum es un error de validación, no persiste', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id, ['tipo_aplicacion' => 'floracion']))
        ->assertSessionHasErrors('tipo_aplicacion');

    expect(OrdenAplicacion::query()->count())->toBe(0);
});

it('una orden creada sin tipo_aplicacion (fuera del formulario del panel) queda en desarrollo', function () {
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $orden = OrdenAplicacion::query()->create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '15.00',
        'fecha_emision' => Carbon::today()->toDateString(),
        'estado' => EstadoOrdenAplicacion::Emitida,
    ]);

    expect($orden->fresh()->tipo_aplicacion)->toBe(TipoAplicacion::Desarrollo);
});

it('litros_ha menor o igual a cero es un error de validación, no persiste', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id, ['litros_ha' => '0']))
        ->assertSessionHasErrors('litros_ha');

    expect(OrdenAplicacion::query()->count())->toBe(0);
});

it('nro_aplicacion menor a uno es un error de validación, no persiste', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id, ['nro_aplicacion' => 0]))
        ->assertSessionHasErrors('nro_aplicacion');

    expect(OrdenAplicacion::query()->count())->toBe(0);
});

it('humedad_min_pct mayor que humedad_max_pct es un error de validación, no persiste', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id, [
        'humedad_min_pct' => '80',
        'humedad_max_pct' => '40',
    ]))->assertSessionHasErrors('humedad_min_pct');

    expect(OrdenAplicacion::query()->count())->toBe(0);
});

it('una orden vigente no admite edición: el caso de uso la rechaza aunque el link ya esté oculto', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $orden = OrdenAplicacion::query()->create([
        ...payloadOrden($contrato->id, $lote->id),
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);

    $this->put(route('panel.ordenes.update', $orden), payloadOrden($contrato->id, $lote->id, ['nro_aplicacion' => 9]))
        ->assertRedirect(route('panel.ordenes.index'))
        ->assertSessionHasErrors('estado');

    expect($orden->fresh()->nro_aplicacion)->toBe(1);
});

it('una orden vigente no se puede eliminar directamente', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $orden = OrdenAplicacion::query()->create([
        ...payloadOrden($contrato->id, $lote->id),
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);

    $this->delete(route('panel.ordenes.destroy', $orden))
        ->assertRedirect(route('panel.ordenes.index'))
        ->assertSessionHasErrors('estado');

    expect($orden->fresh()->trashed())->toBeFalse();
});

it('registra en bitácora el alta, la edición y la baja de una orden', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id));
    $orden = OrdenAplicacion::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'ope_ordenes_aplicacion')
        ->where('registro_id', $orden->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['nro_aplicacion'])->toBe(1);

    $this->put(route('panel.ordenes.update', $orden), payloadOrden($contrato->id, $lote->id, ['nro_aplicacion' => 2]))
        ->assertRedirect(route('panel.ordenes.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'ope_ordenes_aplicacion')
        ->where('registro_id', $orden->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['nro_aplicacion'])->toBe(2);

    $this->delete(route('panel.ordenes.destroy', $orden))
        ->assertRedirect(route('panel.ordenes.index'));

    Bitacora::query()
        ->where('tabla', 'ope_ordenes_aplicacion')
        ->where('registro_id', $orden->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja una orden emitida por soft delete: no aparece en el índice y un segundo intento da 404', function () {
    [$encargado, $idRol] = usuarioConRolParaOrdenes('encargado', 'encargado_operaciones');
    entrarAlPanelParaOrdenes($encargado, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id, [
        'observaciones' => 'ORDEN-MARCA-BAJA',
    ]));
    $orden = OrdenAplicacion::query()->sole();

    $this->delete(route('panel.ordenes.destroy', $orden))
        ->assertRedirect(route('panel.ordenes.index'));

    $borrada = OrdenAplicacion::withTrashed()->findOrFail($orden->id);
    expect($borrada->trashed())->toBeTrue();

    $this->get(route('panel.ordenes.index'))
        ->assertOk()
        ->assertDontSee('ORDEN-MARCA-BAJA');

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.ordenes.destroy', $orden))->assertNotFound();
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaOrdenes('piloto.curioso', 'piloto');
    entrarAlPanelParaOrdenes($piloto, $idRol);
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $orden = OrdenAplicacion::query()->create(payloadOrden($contrato->id, $lote->id) + ['estado' => EstadoOrdenAplicacion::Emitida]);

    $this->get(route('panel.ordenes.index'))->assertForbidden();
    $this->get(route('panel.ordenes.create'))->assertForbidden();
    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id))->assertForbidden();
    $this->get(route('panel.ordenes.edit', $orden))->assertForbidden();
    $this->put(route('panel.ordenes.update', $orden), payloadOrden($contrato->id, $lote->id))->assertForbidden();
    $this->post(route('panel.ordenes.activar', $orden))->assertForbidden();
    $this->delete(route('panel.ordenes.destroy', $orden))->assertForbidden();

    expect(OrdenAplicacion::query()->count())->toBe(1)
        ->and($orden->fresh()->estado)->toBe(EstadoOrdenAplicacion::Emitida);
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    [$multirol, $idEncargado] = usuarioConRolParaOrdenes('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    entrarAlPanelParaOrdenes($multirol, $idPiloto);
    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id))->assertForbidden();
    expect(OrdenAplicacion::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaOrdenes($multirol, $idEncargado);
    $this->post(route('panel.ordenes.store'), payloadOrden($contrato->id, $lote->id))->assertRedirect();
    expect(OrdenAplicacion::query()->count())->toBe(1);
});

it('operaciones.orden.activar está separado de .editar: un rol con uno y no el otro no puede hacer la acción que no tiene', function () {
    $cliente = clienteParaOrdenes();
    $contrato = contratoParaOrdenes($cliente->id);
    $lote = loteParaOrdenes(campoParaOrdenes($cliente->id));

    $rolSoloEditar = rolConPermisosParaOrdenes('solo_editar_ordenes', ['operaciones.orden.ver', 'operaciones.orden.editar']);
    $usuarioEditor = SecUser::factory()->create(['username' => 'editor.ordenes', 'password' => 'Secreta123']);
    $pivoteEditor = new SecUserRole(['id_user' => $usuarioEditor->id, 'id_role' => $rolSoloEditar->id]);
    $pivoteEditor->created_by = $usuarioEditor->id;
    $pivoteEditor->updated_by = $usuarioEditor->id;
    $pivoteEditor->save();

    $ordenParaEditor = OrdenAplicacion::query()->create(payloadOrden($contrato->id, $lote->id) + ['estado' => EstadoOrdenAplicacion::Emitida]);

    entrarAlPanelParaOrdenes($usuarioEditor, $rolSoloEditar->id);
    $this->post(route('panel.ordenes.activar', $ordenParaEditor))->assertForbidden();
    $this->put(route('panel.ordenes.update', $ordenParaEditor), payloadOrden($contrato->id, $lote->id, ['nro_aplicacion' => 3]))
        ->assertRedirect(route('panel.ordenes.index'));
    expect($ordenParaEditor->fresh()->nro_aplicacion)->toBe(3)
        ->and($ordenParaEditor->fresh()->estado)->toBe(EstadoOrdenAplicacion::Emitida);

    $rolSoloActivar = rolConPermisosParaOrdenes('solo_activar_ordenes', ['operaciones.orden.ver', 'operaciones.orden.activar']);
    $usuarioActivador = SecUser::factory()->create(['username' => 'activador.ordenes', 'password' => 'Secreta123']);
    $pivoteActivador = new SecUserRole(['id_user' => $usuarioActivador->id, 'id_role' => $rolSoloActivar->id]);
    $pivoteActivador->created_by = $usuarioActivador->id;
    $pivoteActivador->updated_by = $usuarioActivador->id;
    $pivoteActivador->save();

    $ordenParaActivador = OrdenAplicacion::query()->create(payloadOrden($contrato->id, $lote->id, ['nro_aplicacion' => 5]) + ['estado' => EstadoOrdenAplicacion::Emitida]);

    entrarAlPanelParaOrdenes($usuarioActivador, $rolSoloActivar->id);
    $this->put(route('panel.ordenes.update', $ordenParaActivador), payloadOrden($contrato->id, $lote->id, ['nro_aplicacion' => 6]))
        ->assertForbidden();
    $this->post(route('panel.ordenes.activar', $ordenParaActivador))->assertRedirect(route('panel.ordenes.index'));
    expect($ordenParaActivador->fresh()->estado)->toBe(EstadoOrdenAplicacion::Vigente);
});

it('publica el ítem de menú de órdenes gateado por operaciones.orden.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.operacion.items.ordenes')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'operaciones.orden.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.ordenes.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
