<?php

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
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
    return Cliente::query()->create(['razon_social' => $razonSocial, 'tipo_persona' => 'juridica']);
}

/** Propiedad de prueba del cliente (ADR 0018): el campo ahora cuelga de una propiedad, no directo del cliente. */
function propiedadDeCamposDePrueba(Cliente $cliente, string $nombre = 'Propiedad Norte'): Propiedad
{
    return Propiedad::query()->create([
        'cliente_id' => $cliente->id,
        'nombre' => $nombre,
        'ubicacion' => 'Km 12, ruta a Montero',
    ]);
}

/** Payload mínimo válido de alta/edición: propiedad + nombre + un lote. */
function payloadCampo(int $propiedadId, array $overrides = []): array
{
    return array_merge([
        'propiedad_id' => $propiedadId,
        'nombre' => 'Campo Norte',
        'lotes' => [
            ['codigo' => 'L-01', 'hectareas' => '15.50'],
        ],
    ], $overrides);
}

/** Campaña de prueba del cliente (HU-72, tarea 88: campaña del generador de alta masiva). */
function campaniaDeCamposDePrueba(int $clienteId, string $codigo = '2025-2026'): Campania
{
    return Campania::query()->create([
        'cliente_id' => $clienteId,
        'codigo' => $codigo,
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'abierta',
    ]);
}

/** Id de un cultivo del catálogo demo (HU-72, tarea 88). */
function cultivoIdDeCamposDePrueba(string $nombre = 'Soya'): int
{
    return (int) Cultivo::query()->where('nombre', $nombre)->value('id');
}

/** Orden de aplicación mínima válida asociada a un lote (fuera del alcance de Comercial, pero necesaria para el test de historial). */
function crearOrdenAplicacionParaLote(Lote $lote): void
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
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id))
        ->assertRedirect(route('panel.campos.index'));

    $campo = Campo::query()->where('nombre', 'Campo Norte')->sole();

    expect($campo->propiedad_id)->toBe($propiedad->id)
        ->and($campo->propiedad->cliente_id)->toBe($cliente->id)
        ->and($campo->lotes()->count())->toBe(1)
        ->and($campo->lotes()->first()->codigo)->toBe('L-01')
        ->and((string) $campo->lotes()->first()->hectareas)->toBe('15.50');
});

it('rechaza un lote con hectareas menores o iguales a cero', function () {
    $cliente = clienteDeCamposDePrueba();
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
        'lotes' => [['codigo' => 'L-01', 'hectareas' => '0']],
    ]))->assertSessionHasErrors('lotes.0.hectareas');

    expect(Campo::query()->count())->toBe(0);
});

it('rechaza una geometria mal formada y acepta la geometria ausente', function () {
    $cliente = clienteDeCamposDePrueba();
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
        'lotes' => [['codigo' => 'L-01', 'hectareas' => '10', 'geometria' => 'no es json']],
    ]))->assertSessionHasErrors('lotes.0.geometria');

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
        'lotes' => [['codigo' => 'L-01', 'hectareas' => '10', 'geometria' => json_encode(['type' => 'Point'])]],
    ]))->assertSessionHasErrors('lotes.0.geometria');

    expect(Campo::query()->count())->toBe(0);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
        'lotes' => [['codigo' => 'L-01', 'hectareas' => '10', 'geometria' => null]],
    ]))->assertRedirect(route('panel.campos.index'));

    $lote = Campo::query()->sole()->lotes()->sole();
    expect($lote->geometria)->toBeNull();
});

it('acepta una geometria GeoJSON Polygon minima', function () {
    $cliente = clienteDeCamposDePrueba();
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $geometria = json_encode(['type' => 'Polygon', 'coordinates' => [[[-63.1, -17.7], [-63.1, -17.8], [-63.05, -17.8], [-63.1, -17.7]]]]);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
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
    $propiedad = propiedadDeCamposDePrueba($cliente);
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
    $propiedad = propiedadDeCamposDePrueba($cliente);
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
    $propiedad = propiedadDeCamposDePrueba($cliente);
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
    $propiedad = propiedadDeCamposDePrueba($cliente);
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
    $propiedad = propiedadDeCamposDePrueba($cliente);
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
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $geometria = json_encode(['type' => 'Polygon', 'coordinates' => [[[-63.1, -17.7], [-63.1, -17.8], [-63.05, -17.8], [-63.1, -17.7]]]]);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
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
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id));

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
        payloadCampo($propiedad->id, [
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
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id));
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
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id));
    $campo = Campo::query()->sole();
    $lote = $campo->lotes()->sole();

    crearOrdenAplicacionParaLote($lote);

    // El formulario enviado ya NO trae el lote existente: equivale a
    // pedir que se elimine — decisión de esta tarea: se rechaza entero.
    $this->put(
        route('panel.campos.update', $campo),
        payloadCampo($propiedad->id, [
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
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id));

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
        'lotes' => [['codigo' => 'L-99', 'hectareas' => '3']],
    ]))->assertSessionHasErrors('nombre');

    expect(Campo::query()->count())->toBe(1);
});

// HU-73 (tarea 89): desnivel y limpieza del lote — catálogos cerrados
// aparte de `restricciones` (texto libre). Cubre los dos Request de este
// controlador (`CrearCampoRequest`/`ActualizarCampoRequest`).

it('el formulario ofrece los selects de desnivel y limpieza junto a restricciones', function () {
    $cliente = clienteDeCamposDePrueba();
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $respuesta = $this->get(route('panel.campos.create'))->assertOk();

    $respuesta->assertSee('name="lotes[0][desnivel]"', escape: false)
        ->assertSee('name="lotes[0][limpieza]"', escape: false)
        ->assertSee('name="lotes[0][restricciones]"', escape: false)
        ->assertSee(__('comercial.campos.lote_desnivel_empinado'), escape: false)
        ->assertSee(__('comercial.campos.lote_limpieza_muchos_obstaculos'), escape: false);
});

it('rechaza un desnivel o limpieza fuera de catalogo en el alta y en la edicion de un campo', function () {
    $cliente = clienteDeCamposDePrueba();
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
        'lotes' => [['codigo' => 'L-01', 'hectareas' => '10', 'desnivel' => 'montanioso']],
    ]))->assertSessionHasErrors('lotes.0.desnivel');

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
        'lotes' => [['codigo' => 'L-01', 'hectareas' => '10', 'limpieza' => 'sucio']],
    ]))->assertSessionHasErrors('lotes.0.limpieza');

    expect(Campo::query()->count())->toBe(0);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
        'lotes' => [['codigo' => 'L-01', 'hectareas' => '10', 'desnivel' => 'varios', 'limpieza' => 'algunos_obstaculos']],
    ]))->assertRedirect(route('panel.campos.index'));

    $campo = Campo::query()->sole();
    $lote = $campo->lotes()->sole();
    expect($lote->desnivel)->toBe('varios')
        ->and($lote->limpieza)->toBe('algunos_obstaculos');

    $this->put(route('panel.campos.update', $campo), payloadCampo($propiedad->id, [
        'lotes' => [['id' => $lote->id, 'codigo' => 'L-01', 'hectareas' => '10', 'desnivel' => 'no-valido']],
    ]))->assertSessionHasErrors('lotes.0.desnivel');
});

it('un lote existente sin desnivel ni limpieza se sigue editando sin que la validacion los fuerce', function () {
    $cliente = clienteDeCamposDePrueba();
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id));
    $campo = Campo::query()->sole();
    $lote = $campo->lotes()->sole();
    expect($lote->desnivel)->toBeNull()->and($lote->limpieza)->toBeNull();

    $this->put(route('panel.campos.update', $campo), payloadCampo($propiedad->id, [
        'lotes' => [['id' => $lote->id, 'codigo' => 'L-01', 'hectareas' => '20']],
    ]))->assertRedirect(route('panel.campos.index'));

    expect($lote->fresh()?->desnivel)->toBeNull()
        ->and($lote->fresh()?->limpieza)->toBeNull();
});

it('el codigo de lote duplicado para el mismo campo es un error de validacion, no un QueryException', function () {
    $cliente = clienteDeCamposDePrueba();
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
        'lotes' => [
            ['codigo' => 'L-01', 'hectareas' => '10'],
            ['codigo' => 'L-01', 'hectareas' => '5'],
        ],
    ]))->assertSessionHasErrors('lotes');

    expect(Campo::query()->count())->toBe(0);
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    $cliente = clienteDeCamposDePrueba();
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$piloto, $idRol] = usuarioConRolParaCampos('piloto.curioso', 'piloto');
    entrarAlPanelParaCampos($piloto, $idRol);

    $campo = Campo::query()->create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo Existente']);

    $this->get(route('panel.campos.index'))->assertForbidden();
    $this->get(route('panel.campos.create'))->assertForbidden();
    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id))->assertForbidden();
    $this->get(route('panel.campos.edit', $campo))->assertForbidden();
    $this->put(route('panel.campos.update', $campo), payloadCampo($propiedad->id))->assertForbidden();
    $this->delete(route('panel.campos.destroy', $campo))->assertForbidden();

    expect(Campo::query()->count())->toBe(1)
        ->and($campo->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    $cliente = clienteDeCamposDePrueba();
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$multirol, $idEncargado] = usuarioConRolParaCampos('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaCampos($multirol, $idPiloto);
    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id))->assertForbidden();
    expect(Campo::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaCampos($multirol, $idEncargado);
    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id))->assertRedirect();
    expect(Campo::query()->count())->toBe(1);
});

it('publica el item de menu de campos gateado por comercial.campo.ver', function () {
    // ADR 0018: "Propiedades" pasó a ser el ítem de la entidad nueva
    // (`comercial.propiedad.ver`, ver `GestionPropiedadesPanelTest`); este
    // ítem, que sigue apuntando a `panel.campos.index`, se llama de nuevo
    // "Campos".
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.comercial.items.campos')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'comercial.campo.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.campos.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});

// HU-72 (tarea 88): alta masiva de lotes — el generador del formulario solo
// rellena el mismo array `lotes[]` que ya se posteaba (tarea 35); lo nuevo es
// `cultivo_id`/`campania_id` a nivel formulario, que siembran cada lote
// recién creado. Tests end to end sobre `POST /panel/campos`, complementarios
// a los del caso de uso en `CrearCampoConSiembraTest.php`.

it('un POST con N lotes generados y un cultivo por defecto crea N lotes y N filas de siembra', function () {
    $cliente = clienteDeCamposDePrueba();
    $propiedad = propiedadDeCamposDePrueba($cliente);
    $campania = campaniaDeCamposDePrueba($cliente->id);
    $cultivoId = cultivoIdDeCamposDePrueba('Soya');
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
        'lotes' => [
            ['codigo' => 'Lote 1', 'hectareas' => '10.00'],
            ['codigo' => 'Lote 2', 'hectareas' => '10.00'],
            ['codigo' => 'Lote 3', 'hectareas' => '10.00'],
        ],
        'cultivo_id' => $cultivoId,
        'campania_id' => $campania->id,
    ]))->assertRedirect(route('panel.campos.index'));

    $campo = Campo::query()->sole();
    expect($campo->lotes()->count())->toBe(3);

    $siembras = LoteCampania::query()->where('campania_id', $campania->id)->get();
    expect($siembras)->toHaveCount(3);

    foreach ($campo->lotes as $lote) {
        $siembra = $siembras->firstWhere('lote_id', $lote->id);

        expect($siembra)->not->toBeNull()
            ->and($siembra->cultivo_id)->toBe($cultivoId)
            ->and((string) $siembra->hectareas_sembradas)->toBe((string) $lote->hectareas);
    }
});

it('un alta de campo sin cultivo_id ni campania_id sigue sin crear ninguna fila de siembra', function () {
    $cliente = clienteDeCamposDePrueba();
    $propiedad = propiedadDeCamposDePrueba($cliente);
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id))
        ->assertRedirect(route('panel.campos.index'));

    expect(Campo::query()->sole()->lotes()->count())->toBe(1)
        ->and(LoteCampania::query()->count())->toBe(0);
});

it('una campaña de otro cliente en el generador es un error de validacion, no un 500, y no deja el campo a medio crear', function () {
    $cliente = clienteDeCamposDePrueba('Cliente dueño de la propiedad');
    $otroCliente = clienteDeCamposDePrueba('Otro cliente');
    $propiedad = propiedadDeCamposDePrueba($cliente);
    $campaniaAjena = campaniaDeCamposDePrueba($otroCliente->id);
    $cultivoId = cultivoIdDeCamposDePrueba('Soya');
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
        'cultivo_id' => $cultivoId,
        'campania_id' => $campaniaAjena->id,
    ]))->assertSessionHasErrors('campania_id');

    expect(Campo::query()->count())->toBe(0)
        ->and(Lote::query()->count())->toBe(0);
});

it('renombrar y redibujar despues, desde la ficha existente, no borra la siembra ni el cultivo del lote generado', function () {
    $cliente = clienteDeCamposDePrueba();
    $propiedad = propiedadDeCamposDePrueba($cliente);
    $campania = campaniaDeCamposDePrueba($cliente->id);
    $cultivoId = cultivoIdDeCamposDePrueba('Soya');
    [$encargado, $idRol] = usuarioConRolParaCampos('encargado', 'encargado_operaciones');
    entrarAlPanelParaCampos($encargado, $idRol);

    $this->post(route('panel.campos.store'), payloadCampo($propiedad->id, [
        'lotes' => [['codigo' => 'Lote 1', 'hectareas' => '10.00']],
        'cultivo_id' => $cultivoId,
        'campania_id' => $campania->id,
    ]));

    $campo = Campo::query()->sole();
    $lote = $campo->lotes()->sole();
    $siembra = LoteCampania::query()->where('lote_id', $lote->id)->where('campania_id', $campania->id)->sole();

    $geometria = json_encode(['type' => 'Polygon', 'coordinates' => [[[-63.1, -17.7], [-63.1, -17.8], [-63.05, -17.8], [-63.1, -17.7]]]]);

    $this->put(route('panel.campos.update', $campo), [
        'propiedad_id' => $propiedad->id,
        'nombre' => $campo->nombre,
        'lotes' => [[
            'id' => $lote->id,
            'codigo' => 'Lote Norte renombrado',
            'hectareas' => '10.00',
            'geometria' => $geometria,
        ]],
    ])->assertRedirect(route('panel.campos.index'));

    $lote->refresh();
    expect($lote->codigo)->toBe('Lote Norte renombrado')
        ->and($lote->geometria)->not->toBeNull();

    $siembra->refresh();
    expect($siembra->trashed())->toBeFalse()
        ->and($siembra->cultivo_id)->toBe($cultivoId)
        ->and(LoteCampania::query()->where('lote_id', $lote->id)->where('campania_id', $campania->id)->exists())->toBeTrue();
});
