<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion;
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
 * HU-24 (tarea 35): administración de campos y sus lotes — tercer ABM
 * completo del panel, mismo molde que HU-22 (clientes, tarea 33): un campo
 * se crea/edita con sus lotes en la misma operación, sin pantalla propia
 * para lotes. Permisos evaluados contra el ROL ACTIVO de la sesión, nunca la
 * unión de los roles del usuario (invariante 10 de CLAUDE.md). Mismo patrón
 * de asserts que tests/Feature/Comercial/GestionClientesPanelTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaCampos(string $username, string $rol): array
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
function entrarAlPanelParaCampos(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function clienteDeCamposDePrueba(string $razonSocial = 'Agropecuaria del Valle S.R.L.'): Cliente
{
    return Cliente::query()->create(['razon_social' => $razonSocial]);
}

/** Payload mínimo válido de alta/edición: cliente + nombre + un lote. */
function payloadCampo(int $clienteId, array $overrides = []): array
{
    return array_merge([
        'cliente_id' => $clienteId,
        'nombre' => 'Campo Norte',
        'ubicacion' => 'Km 12, ruta a Montero',
        'lotes' => [
            ['codigo' => 'L-01', 'hectareas' => '15.50'],
        ],
    ], $overrides);
}

/** Orden de aplicación mínima válida asociada a un lote (fuera del alcance de Comercial, pero necesaria para el test de historial). */
function crearOrdenAplicacionParaLote(Lote $lote): void
{
    $contrato = Contrato::query()->create([
        'cliente_id' => $lote->campo->cliente_id,
        'hectareas_contratadas' => '10.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '100.00',
        'monto_total' => '1000.00',
        'fecha_inicio' => now()->toDateString(),
        'estado' => 'borrador',
    ]);

    DB::table('ope_ordenes_aplicacion')->insert([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '20.00',
        'fecha_emision' => now()->toDateString(),
        'estado' => 'emitida',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('da de alta un campo con al menos un lote', function () {
    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($cliente->id))
        ->assertRedirect(route('panel.campos.index'));

    $campo = Campo::query()->where('nombre', 'Campo Norte')->sole();

    expect($campo->cliente_id)->toBe($cliente->id)
        ->and($campo->lotes()->count())->toBe(1)
        ->and($campo->lotes()->first()->codigo)->toBe('L-01')
        ->and((string) $campo->lotes()->first()->hectareas)->toBe('15.50');
});

it('rechaza un lote con hectareas menores o iguales a cero', function () {
    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($cliente->id, [
        'lotes' => [['codigo' => 'L-01', 'hectareas' => '0']],
    ]))->assertSessionHasErrors('lotes.0.hectareas');

    expect(Campo::query()->count())->toBe(0);
});

it('rechaza una geometria mal formada y acepta la geometria ausente', function () {
    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($cliente->id, [
        'lotes' => [['codigo' => 'L-01', 'hectareas' => '10', 'geometria' => 'no es json']],
    ]))->assertSessionHasErrors('lotes.0.geometria');

    $this->post(route('panel.campos.store'), payloadCampo($cliente->id, [
        'lotes' => [['codigo' => 'L-01', 'hectareas' => '10', 'geometria' => json_encode(['type' => 'Point'])]],
    ]))->assertSessionHasErrors('lotes.0.geometria');

    expect(Campo::query()->count())->toBe(0);

    $this->post(route('panel.campos.store'), payloadCampo($cliente->id, [
        'lotes' => [['codigo' => 'L-01', 'hectareas' => '10', 'geometria' => null]],
    ]))->assertRedirect(route('panel.campos.index'));

    $lote = Campo::query()->sole()->lotes()->sole();
    expect($lote->geometria)->toBeNull();
});

it('acepta una geometria GeoJSON Polygon minima', function () {
    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $geometria = json_encode(['type' => 'Polygon', 'coordinates' => [[[-63.1, -17.7], [-63.1, -17.8], [-63.05, -17.8], [-63.1, -17.7]]]]);

    $this->post(route('panel.campos.store'), payloadCampo($cliente->id, [
        'lotes' => [['codigo' => 'L-01', 'hectareas' => '10', 'geometria' => $geometria]],
    ]))->assertRedirect(route('panel.campos.index'));

    $lote = Campo::query()->sole()->lotes()->sole();
    expect($lote->geometria)->toBe(['type' => 'Polygon', 'coordinates' => [[[-63.1, -17.7], [-63.1, -17.8], [-63.05, -17.8], [-63.1, -17.7]]]]);
});

it('el formulario ofrece el editor de mapa, no un textarea de GeoJSON', function () {
    // El perímetro se dibuja sobre imagen satelital desde la tarea 68. El
    // valor sigue viajando en un input con el MISMO nombre —`lotes[0][geometria]`—
    // así que el Form Request y todos los tests de arriba no cambian: lo que
    // cambia es cómo se produce ese string.
    //
    // Este test es la aduana de ese contrato: si alguien vuelve al textarea o
    // le cambia el nombre al input, el editor deja de escribir donde el
    // servidor lee y la geometría se pierde en silencio.
    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $respuesta = $this->get(route('panel.campos.create'))->assertOk();

    $respuesta->assertSee('data-ag-lote-mapa', escape: false)
        ->assertSee('name="lotes[0][geometria]"', escape: false)
        ->assertSee('type="hidden"', escape: false)
        ->assertDontSee('<textarea name="lotes[0][geometria]"', escape: false);
});

it('el editor de mapa trae barra de acciones propia y pantalla completa, con textos en español', function () {
    // Tarea 79 (HU-56): reemplaza el chrome nativo de Leaflet-Geoman por una
    // barra propia con Material Symbols y `title`/`aria-label` en español —
    // este test es la aduana de esas siete acciones más el botón de pantalla
    // completa, todas con su etiqueta accesible.
    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $respuesta = $this->get(route('panel.campos.create'))->assertOk();

    foreach (['dibujar', 'editar', 'mover', 'borrar', 'deshacer', 'centrar', 'capa'] as $accion) {
        $respuesta->assertSee("data-ag-lote-accion=\"{$accion}\"", escape: false);
    }

    $respuesta->assertSee('data-ag-lote-mapa-boton-pantalla-completa', escape: false)
        ->assertSee('Dibujar perímetro', escape: false)
        ->assertSee('Editar vértices', escape: false)
        ->assertSee('Centrar en el lote', escape: false)
        ->assertSee('Pantalla completa', escape: false)
        ->assertSee('role="toolbar"', escape: false);
});

it('sin llave de google configurada, el editor usa leaflet y la llave no aparece en el HTML', function () {
    // Tarea 79: aunque nunca se hubiera configurado una llave, esta es la
    // aduana explícita del criterio de aceptación — un secreto que no está
    // no puede "aparecer" por accidente en ningún render futuro de esta vista.
    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $respuesta = $this->get(route('panel.campos.create'))->assertOk();

    $respuesta->assertSee('data-ag-lote-mapa-proveedor="leaflet"', escape: false)
        ->assertDontSee('data-ag-lote-mapa-google-key', escape: false);
});

it('con llave de google configurada, el editor pasa a google con la llave', function () {
    Configuracion::query()->create([
        'clave' => 'mapas.google_maps_api_key',
        'valor' => 'AIzaSyD-prueba-0000',
        'grupo' => 'mapas',
        'es_secreto' => true,
    ]);

    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $respuesta = $this->get(route('panel.campos.create'))->assertOk();

    $respuesta->assertSee('data-ag-lote-mapa-proveedor="google"', escape: false)
        ->assertSee('data-ag-lote-mapa-google-key="AIzaSyD-prueba-0000"', escape: false);
});

it('con llave configurada pero proveedor_preferido=leaflet, la llave sigue sin aparecer en el HTML', function () {
    Configuracion::query()->create([
        'clave' => 'mapas.google_maps_api_key',
        'valor' => 'AIzaSyD-prueba-0000',
        'grupo' => 'mapas',
        'es_secreto' => true,
    ]);
    Configuracion::query()->create([
        'clave' => 'mapas.proveedor_preferido',
        'valor' => 'leaflet',
        'grupo' => 'mapas',
        'es_secreto' => false,
    ]);

    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $respuesta = $this->get(route('panel.campos.create'))->assertOk();

    $respuesta->assertSee('data-ag-lote-mapa-proveedor="leaflet"', escape: false)
        ->assertDontSee('AIzaSyD-prueba-0000', escape: false);
});

it('conserva la geometria dibujada al reabrir el formulario de edicion', function () {
    // El editor lee el perímetro guardado desde el `value` del input oculto:
    // si el formulario de edición no lo emite, cada guardado posterior borra
    // el polígono que ya estaba.
    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $geometria = json_encode(['type' => 'Polygon', 'coordinates' => [[[-63.1, -17.7], [-63.1, -17.8], [-63.05, -17.8], [-63.1, -17.7]]]]);

    $this->post(route('panel.campos.store'), payloadCampo($cliente->id, [
        'lotes' => [['codigo' => 'L-01', 'hectareas' => '10', 'geometria' => $geometria]],
    ]));

    $campo = Campo::query()->sole();

    $this->get(route('panel.campos.edit', $campo))
        ->assertOk()
        ->assertSee('data-ag-lote-geometria', escape: false)
        ->assertSee('-63.1', escape: false);
});

it('registra en bitacora el alta, la edicion y la baja de un campo', function () {
    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($cliente->id));

    $campo = Campo::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'com_campos')
        ->where('registro_id', $campo->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['nombre'])->toBe('Campo Norte');

    $lote = $campo->lotes()->sole();

    $this->put(
        route('panel.campos.update', $campo),
        payloadCampo($cliente->id, [
            'nombre' => 'Campo Norte (renombrado)',
            'lotes' => [['id' => $lote->id, 'codigo' => 'L-01', 'hectareas' => '15.50']],
        ]),
    )->assertRedirect(route('panel.campos.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'com_campos')
        ->where('registro_id', $campo->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['nombre'])->toBe('Campo Norte (renombrado)');

    $this->delete(route('panel.campos.destroy', $campo))
        ->assertRedirect(route('panel.campos.index'));

    Bitacora::query()
        ->where('tabla', 'com_campos')
        ->where('registro_id', $campo->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja un campo por soft delete: no aparece en el indice y un segundo intento da 404', function () {
    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($cliente->id));
    $campo = Campo::query()->sole();

    $this->delete(route('panel.campos.destroy', $campo))
        ->assertRedirect(route('panel.campos.index'));

    $borrado = Campo::withTrashed()->findOrFail($campo->id);
    expect($borrado->trashed())->toBeTrue();

    $this->get(route('panel.campos.index'))
        ->assertOk()
        ->assertDontSee('Campo Norte');

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.campos.destroy', $campo))->assertNotFound();
});

it('rechaza quitar del formulario un lote con una orden de aplicacion asociada', function () {
    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($cliente->id));
    $campo = Campo::query()->sole();
    $lote = $campo->lotes()->sole();

    crearOrdenAplicacionParaLote($lote);

    // El formulario enviado ya NO trae el lote existente: equivale a
    // pedir que se elimine — decisión de esta tarea: se rechaza entero.
    $this->put(
        route('panel.campos.update', $campo),
        payloadCampo($cliente->id, [
            'nombre' => 'Campo Norte',
            'lotes' => [['codigo' => 'L-02', 'hectareas' => '5']],
        ]),
    )->assertSessionHasErrors('lotes');

    expect($lote->fresh()?->trashed())->toBeFalse()
        ->and($campo->lotes()->count())->toBe(1)
        ->and(Lote::query()->where('codigo', 'L-02')->exists())->toBeFalse();
});

it('el nombre de campo duplicado para el mismo cliente es un error de validacion, no un QueryException', function () {
    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($cliente->id));

    $this->post(route('panel.campos.store'), payloadCampo($cliente->id, [
        'lotes' => [['codigo' => 'L-99', 'hectareas' => '3']],
    ]))->assertSessionHasErrors('nombre');

    expect(Campo::query()->count())->toBe(1);
});

it('el codigo de lote duplicado para el mismo campo es un error de validacion, no un QueryException', function () {
    $cliente = clienteDeCamposDePrueba();
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($cliente->id, [
        'lotes' => [
            ['codigo' => 'L-01', 'hectareas' => '10'],
            ['codigo' => 'L-01', 'hectareas' => '5'],
        ],
    ]))->assertSessionHasErrors('lotes');

    expect(Campo::query()->count())->toBe(0);
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    $cliente = clienteDeCamposDePrueba();
    [$piloto, $idRol] = usuarioConRolParaCampos('piloto.curioso', 'piloto');
    entrarAlPanelParaCampos($piloto, $idRol);

    $campo = Campo::query()->create(['cliente_id' => $cliente->id, 'nombre' => 'Campo Existente']);

    $this->get(route('panel.campos.index'))->assertForbidden();
    $this->get(route('panel.campos.create'))->assertForbidden();
    $this->post(route('panel.campos.store'), payloadCampo($cliente->id))->assertForbidden();
    $this->get(route('panel.campos.edit', $campo))->assertForbidden();
    $this->put(route('panel.campos.update', $campo), payloadCampo($cliente->id))->assertForbidden();
    $this->delete(route('panel.campos.destroy', $campo))->assertForbidden();

    expect(Campo::query()->count())->toBe(1)
        ->and($campo->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    $cliente = clienteDeCamposDePrueba();
    [$multirol, $idEncargado] = usuarioConRolParaCampos('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaCampos($multirol, $idPiloto);
    $this->post(route('panel.campos.store'), payloadCampo($cliente->id))->assertForbidden();
    expect(Campo::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaCampos($multirol, $idEncargado);
    $this->post(route('panel.campos.store'), payloadCampo($cliente->id))->assertRedirect();
    expect(Campo::query()->count())->toBe(1);
});

it('publica el item de menu de propiedades gateado por comercial.campo.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.comercial.items.propiedades')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'comercial.campo.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.campos.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
