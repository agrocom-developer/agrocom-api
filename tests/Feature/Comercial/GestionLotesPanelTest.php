<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * Tarea 77 (HU-54, etapa 2): ficha propia de un lote — listado con filtro
 * por cliente/propiedad y búsqueda por código, alta, edición y baja lógica
 * de un lote suelto. Mismo caso de uso de guardado que `CamposController`
 * (ver `GuardadoLote`/`VerificadorHistorialLote`), así que estos tests no
 * repiten los de `GestionCamposPanelTest` sobre geometría/duplicados — solo
 * lo específico de la ficha propia: filtro, alta/edición sin pasar por el
 * campo, reasignación de propiedad y el 403 por URL.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaLotes(string $username, string $rol): array
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
function entrarAlPanelParaLotes(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function campoDeLotesDePrueba(?Cliente $cliente = null, string $nombre = 'Campo Norte'): Campo
{
    $cliente ??= Cliente::query()->create(['razon_social' => 'Agropecuaria del Valle S.R.L.', 'tipo_persona' => 'juridica']);

    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);

    return Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => $nombre]);
}

/**
 * Payload mínimo válido de alta/edición de un lote suelto — anidado bajo
 * `lote[...]`, igual que lo postea el HTML real (`campos/_lote-fila.blade.php`
 * con `prefijo: 'lote'`). Antes viajaba aplanado y enmascaraba el bug de
 * nesting entre la vista y `CrearLoteRequest`/`ActualizarLoteRequest`.
 */
function payloadLote(int $campoId, array $overrides = []): array
{
    return [
        'campo_id' => $campoId,
        'lote' => array_merge([
            'codigo' => 'L-01',
            'hectareas' => '15.50',
        ], $overrides),
    ];
}

function crearOrdenAplicacionParaLoteDePrueba(Lote $lote): void
{
    $contrato = Contrato::query()->create([
        'cliente_id' => $lote->campo->propiedad->cliente_id,
        'hectareas_contratadas' => '10.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '100.00',
        'monto_total' => '1000.00',
        'fecha_inicio' => now()->toDateString(),
        'estado' => 'borrador',
    ]);

    $ordenId = DB::table('ope_ordenes_aplicacion')->insertGetId([
        'contrato_id' => $contrato->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '20.00',
        'fecha_emision' => now()->toDateString(),
        'estado' => 'emitida',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // HU-92 (tarea 107): el lote de la orden ya no es una columna propia,
    // se arma como fila de `ope_orden_lotes`.
    DB::table('ope_orden_lotes')->insert([
        'orden_id' => $ordenId,
        'lote_id' => $lote->id,
        'hectareas_solicitadas' => $lote->hectareas,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('da de alta un lote suelto desde su propia ficha', function () {
    $campo = campoDeLotesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $this->post(route('panel.lotes.store'), payloadLote($campo->id))
        ->assertRedirect(route('panel.lotes.index'));

    $lote = Lote::query()->where('codigo', 'L-01')->sole();

    expect($lote->campo_id)->toBe($campo->id)
        ->and((string) $lote->hectareas)->toBe('15.50');
});

it('rechaza un lote con hectareas menores o iguales a cero', function () {
    $campo = campoDeLotesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $this->post(route('panel.lotes.store'), payloadLote($campo->id, ['hectareas' => '0']))
        ->assertSessionHasErrors('lote.hectareas');

    expect(Lote::query()->count())->toBe(0);
});

it('el codigo de lote duplicado en la misma propiedad es un error de validacion, no un QueryException', function () {
    $campo = campoDeLotesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $this->post(route('panel.lotes.store'), payloadLote($campo->id));

    $this->post(route('panel.lotes.store'), payloadLote($campo->id))
        ->assertSessionHasErrors('codigo');

    expect(Lote::query()->count())->toBe(1);
});

it('el mismo codigo se permite en dos propiedades distintas', function () {
    $campoA = campoDeLotesDePrueba(nombre: 'Campo Norte');
    $campoB = campoDeLotesDePrueba(nombre: 'Campo Sur');
    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $this->post(route('panel.lotes.store'), payloadLote($campoA->id))
        ->assertRedirect(route('panel.lotes.index'));
    $this->post(route('panel.lotes.store'), payloadLote($campoB->id))
        ->assertRedirect(route('panel.lotes.index'));

    expect(Lote::query()->where('codigo', 'L-01')->count())->toBe(2);
});

it('edita un lote suelto, incluida la reasignacion de propiedad', function () {
    $campoOrigen = campoDeLotesDePrueba(nombre: 'Campo Norte');
    $campoDestino = campoDeLotesDePrueba(cliente: $campoOrigen->propiedad->cliente, nombre: 'Campo Sur');
    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $this->post(route('panel.lotes.store'), payloadLote($campoOrigen->id));
    $lote = Lote::query()->sole();

    $this->put(route('panel.lotes.update', $lote), payloadLote($campoDestino->id, [
        'codigo' => 'L-02',
        'hectareas' => '20.00',
    ]))->assertRedirect(route('panel.lotes.index'));

    $lote->refresh();
    expect($lote->campo_id)->toBe($campoDestino->id)
        ->and($lote->codigo)->toBe('L-02')
        ->and((string) $lote->hectareas)->toBe('20.00');
});

it('acepta una geometria GeoJSON Polygon minima y la conserva al reabrir la edicion', function () {
    $campo = campoDeLotesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $geometria = json_encode(['type' => 'Polygon', 'coordinates' => [[[-63.1, -17.7], [-63.1, -17.8], [-63.05, -17.8], [-63.1, -17.7]]]]);

    $this->post(route('panel.lotes.store'), payloadLote($campo->id, ['geometria' => $geometria]))
        ->assertRedirect(route('panel.lotes.index'));

    $lote = Lote::query()->sole();
    expect($lote->geometria)->toBe(['type' => 'Polygon', 'coordinates' => [[[-63.1, -17.7], [-63.1, -17.8], [-63.05, -17.8], [-63.1, -17.7]]]]);

    $this->get(route('panel.lotes.edit', $lote))
        ->assertOk()
        ->assertSee('data-ag-lote-geometria', escape: false)
        ->assertSee('-63.1', escape: false);
});

it('rechaza una geometria mal formada', function () {
    $campo = campoDeLotesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $this->post(route('panel.lotes.store'), payloadLote($campo->id, ['geometria' => 'no es json']))
        ->assertSessionHasErrors('lote.geometria');

    expect(Lote::query()->count())->toBe(0);
});

// HU-73 (tarea 89): desnivel y limpieza del lote — catálogos cerrados
// aparte de `restricciones` (texto libre). Cubre los dos Request de este
// controlador (`CrearLoteRequest`/`ActualizarLoteRequest`), complementarios
// a los de `GestionCamposPanelTest` sobre el otro punto de entrada.

it('el formulario de la ficha propia tambien ofrece los selects de desnivel y limpieza', function () {
    $campo = campoDeLotesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $respuesta = $this->get(route('panel.lotes.create'))->assertOk();

    $respuesta->assertSee('name="lote[desnivel]"', escape: false)
        ->assertSee('name="lote[limpieza]"', escape: false)
        ->assertSee(__('comercial.campos.lote_desnivel_empinado'), escape: false)
        ->assertSee(__('comercial.campos.lote_limpieza_muchos_obstaculos'), escape: false);
});

it('rechaza un desnivel o limpieza fuera de catalogo en el alta y en la edicion de un lote suelto', function () {
    $campo = campoDeLotesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $this->post(route('panel.lotes.store'), payloadLote($campo->id, ['desnivel' => 'montanioso']))
        ->assertSessionHasErrors('lote.desnivel');

    $this->post(route('panel.lotes.store'), payloadLote($campo->id, ['limpieza' => 'sucio']))
        ->assertSessionHasErrors('lote.limpieza');

    expect(Lote::query()->count())->toBe(0);

    $this->post(route('panel.lotes.store'), payloadLote($campo->id, [
        'desnivel' => 'empinado',
        'limpieza' => 'muchos_obstaculos',
    ]))->assertRedirect(route('panel.lotes.index'));

    $lote = Lote::query()->sole();
    expect($lote->desnivel)->toBe('empinado')
        ->and($lote->limpieza)->toBe('muchos_obstaculos');

    $this->put(route('panel.lotes.update', $lote), payloadLote($campo->id, ['desnivel' => 'no-valido']))
        ->assertSessionHasErrors('lote.desnivel');
});

it('un lote suelto existente sin desnivel ni limpieza se sigue editando sin que la validacion los fuerce', function () {
    $campo = campoDeLotesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $this->post(route('panel.lotes.store'), payloadLote($campo->id));
    $lote = Lote::query()->sole();
    expect($lote->desnivel)->toBeNull()->and($lote->limpieza)->toBeNull();

    $this->put(route('panel.lotes.update', $lote), payloadLote($campo->id, ['hectareas' => '20']))
        ->assertRedirect(route('panel.lotes.index'));

    expect($lote->fresh()?->desnivel)->toBeNull()
        ->and($lote->fresh()?->limpieza)->toBeNull();
});

it('registra en bitacora el alta, la edicion y la baja de un lote', function () {
    $campo = campoDeLotesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $this->post(route('panel.lotes.store'), payloadLote($campo->id));
    $lote = Lote::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'com_lotes')
        ->where('registro_id', $lote->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['codigo'])->toBe('L-01');

    $this->put(route('panel.lotes.update', $lote), payloadLote($campo->id, ['codigo' => 'L-01-B']))
        ->assertRedirect(route('panel.lotes.index'));

    Bitacora::query()
        ->where('tabla', 'com_lotes')
        ->where('registro_id', $lote->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    $this->delete(route('panel.lotes.destroy', $lote))
        ->assertRedirect(route('panel.lotes.index'));

    Bitacora::query()
        ->where('tabla', 'com_lotes')
        ->where('registro_id', $lote->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja un lote por soft delete: no aparece en el indice y un segundo intento da 404', function () {
    $campo = campoDeLotesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $this->post(route('panel.lotes.store'), payloadLote($campo->id));
    $lote = Lote::query()->sole();

    $this->delete(route('panel.lotes.destroy', $lote))
        ->assertRedirect(route('panel.lotes.index'));

    $borrado = Lote::withTrashed()->findOrFail($lote->id);
    expect($borrado->trashed())->toBeTrue();

    $this->get(route('panel.lotes.index'))
        ->assertOk()
        ->assertDontSee('L-01');

    $this->delete(route('panel.lotes.destroy', $lote))->assertNotFound();
});

it('rechaza dar de baja un lote con una orden de aplicacion asociada', function () {
    $campo = campoDeLotesDePrueba();
    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $this->post(route('panel.lotes.store'), payloadLote($campo->id));
    $lote = Lote::query()->sole();

    crearOrdenAplicacionParaLoteDePrueba($lote);

    $this->delete(route('panel.lotes.destroy', $lote))
        ->assertRedirect(route('panel.lotes.index'))
        ->assertSessionHasErrors('lote');

    expect($lote->fresh()?->trashed())->toBeFalse();
});

it('filtra el listado por cliente, por propiedad y por codigo', function () {
    $clienteA = Cliente::query()->create(['razon_social' => 'Agropecuaria del Valle S.R.L.', 'tipo_persona' => 'juridica']);
    $clienteB = Cliente::query()->create(['razon_social' => 'Estancia Los Robles S.A.', 'tipo_persona' => 'juridica']);
    $campoA = campoDeLotesDePrueba($clienteA, 'Campo Norte');
    $campoB = campoDeLotesDePrueba($clienteB, 'Campo Sur');

    Lote::query()->create(['campo_id' => $campoA->id, 'codigo' => 'A-01', 'hectareas' => '10']);
    Lote::query()->create(['campo_id' => $campoB->id, 'codigo' => 'B-01', 'hectareas' => '20']);

    [$encargado, $idRol] = usuarioConRolParaLotes('encargado', 'encargado_operaciones');
    entrarAlPanelParaLotes($encargado, $idRol);

    $this->get(route('panel.lotes.index', ['cliente_id' => $clienteA->id]))
        ->assertOk()
        ->assertSee('A-01', escape: false)
        ->assertDontSee('B-01', escape: false);

    $this->get(route('panel.lotes.index', ['campo_id' => $campoB->id]))
        ->assertOk()
        ->assertSee('B-01', escape: false)
        ->assertDontSee('A-01', escape: false);

    $this->get(route('panel.lotes.index', ['q' => 'A-01']))
        ->assertOk()
        ->assertSee('A-01', escape: false)
        ->assertDontSee('B-01', escape: false);
});

it('un rol sin el permiso recibe 403 en todas las acciones, incluida la entrada directa por URL', function () {
    $campo = campoDeLotesDePrueba();
    [$piloto, $idRol] = usuarioConRolParaLotes('piloto.curioso', 'piloto');
    entrarAlPanelParaLotes($piloto, $idRol);

    $lote = Lote::query()->create(['campo_id' => $campo->id, 'codigo' => 'L-01', 'hectareas' => '10']);

    $this->get(route('panel.lotes.index'))->assertForbidden();
    $this->get(route('panel.lotes.create'))->assertForbidden();
    $this->post(route('panel.lotes.store'), payloadLote($campo->id, ['codigo' => 'L-02']))->assertForbidden();
    $this->get(route('panel.lotes.edit', $lote))->assertForbidden();
    $this->put(route('panel.lotes.update', $lote), payloadLote($campo->id))->assertForbidden();
    $this->delete(route('panel.lotes.destroy', $lote))->assertForbidden();

    expect(Lote::query()->count())->toBe(1)
        ->and($lote->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso de lote en otro rol pero no en el activo', function () {
    $campo = campoDeLotesDePrueba();
    [$multirol, $idEncargado] = usuarioConRolParaLotes('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaLotes($multirol, $idPiloto);
    $this->post(route('panel.lotes.store'), payloadLote($campo->id))->assertForbidden();
    expect(Lote::query()->count())->toBe(0);

    entrarAlPanelParaLotes($multirol, $idEncargado);
    $this->post(route('panel.lotes.store'), payloadLote($campo->id))->assertRedirect();
    expect(Lote::query()->count())->toBe(1);
});
