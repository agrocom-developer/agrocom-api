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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

/** Bytes de un PNG 1x1 real (finfo lo detecta como image/png, mismo criterio que el PDF de GastosPanelTest). */
function logoPngValido(string $nombre = 'logo.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $nombre,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
    );
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

it('un alta rechazada por NIT duplicado no deja el logo subido huérfano en el disco', function () {
    Storage::fake('public');

    [$encargado, $idRol] = usuarioConRolParaClientes('encargado', 'encargado_operaciones');
    entrarAlPanelParaClientes($encargado, $idRol);

    Cliente::query()->create(['razon_social' => 'Cliente Uno S.R.L.', 'nit' => '111222333', 'tipo_persona' => 'juridica']);

    $this->post(route('panel.clientes.store'), payloadCliente([
        'razon_social' => 'Cliente Dos S.R.L.',
        'nit' => '111222333',
        'logo' => logoPngValido(),
    ]))->assertSessionHasErrors('nit');

    expect(Storage::disk('public')->allFiles('logos/clientes'))->toBe([]);
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

/*
 * HU-75 (tarea 91): ficha de cliente ampliada — ubicación de la oficina
 * central, logo (mismo molde que SecDatosEmpresa, ADR 0019) y tres tipos de
 * contacto de oficina nuevos.
 */

it('acepta los tres tipos de contacto nuevos de oficina central en alta y edición', function () {
    [$encargado, $idRol] = usuarioConRolParaClientes('encargado', 'encargado_operaciones');
    entrarAlPanelParaClientes($encargado, $idRol);

    $contactosDeOficina = [
        ['tipo' => 'dueno', 'nombre' => 'Juana Pérez'],
        ['tipo' => 'gerente_general', 'nombre' => 'Gustavo Gómez'],
        ['tipo' => 'finanzas', 'nombre' => 'Fabiana Flores'],
        ['tipo' => 'secretario', 'nombre' => 'Sergio Soto'],
    ];

    $this->post(route('panel.clientes.store'), payloadCliente(['contactos' => $contactosDeOficina]))
        ->assertRedirect(route('panel.clientes.index'));

    $cliente = Cliente::query()->sole();

    expect($cliente->contactos()->pluck('tipo')->map(fn ($tipo) => $tipo->value)->sort()->values()->all())
        ->toBe(['dueno', 'finanzas', 'gerente_general', 'secretario']);

    $this->put(route('panel.clientes.update', $cliente), payloadCliente(['contactos' => $contactosDeOficina]))
        ->assertRedirect(route('panel.clientes.index'));

    expect($cliente->contactos()->count())->toBe(4);
});

it('rechaza un tipo de contacto fuera del catálogo completo de siete valores, en alta y edición', function () {
    [$encargado, $idRol] = usuarioConRolParaClientes('encargado', 'encargado_operaciones');
    entrarAlPanelParaClientes($encargado, $idRol);

    $this->post(route('panel.clientes.store'), payloadCliente([
        'contactos' => [['tipo' => 'tipo_inexistente', 'nombre' => 'Nadie']],
    ]))->assertSessionHasErrors('contactos.0.tipo');

    expect(Cliente::query()->count())->toBe(0);

    $cliente = Cliente::query()->create(['razon_social' => 'Cliente Existente S.R.L.', 'tipo_persona' => 'juridica']);
    $cliente->contactos()->create(['tipo' => 'dueno', 'nombre' => 'Juana Pérez']);

    $this->put(route('panel.clientes.update', $cliente), payloadCliente([
        'contactos' => [['tipo' => 'tipo_inexistente', 'nombre' => 'Nadie']],
    ]))->assertSessionHasErrors('contactos.0.tipo');
});

it('guarda y lee la ubicación de la oficina central, opcional', function () {
    [$encargado, $idRol] = usuarioConRolParaClientes('encargado', 'encargado_operaciones');
    entrarAlPanelParaClientes($encargado, $idRol);

    $this->post(route('panel.clientes.store'), payloadCliente())
        ->assertRedirect(route('panel.clientes.index'));

    expect(Cliente::query()->sole()->ubicacion_oficina)->toBeNull();

    $cliente = Cliente::query()->sole();

    $this->put(
        route('panel.clientes.update', $cliente),
        payloadCliente(['ubicacion_oficina' => 'Av. Banzer km 5, Santa Cruz de la Sierra']),
    )->assertRedirect(route('panel.clientes.index'));

    expect($cliente->fresh()->ubicacion_oficina)->toBe('Av. Banzer km 5, Santa Cruz de la Sierra');
});

it('el formulario de alta muestra el campo de ubicación de oficina y el file-field del logo, sin archivo', function () {
    [$encargado, $idRol] = usuarioConRolParaClientes('encargado', 'encargado_operaciones');
    entrarAlPanelParaClientes($encargado, $idRol);

    $this->get(route('panel.clientes.create'))
        ->assertOk()
        ->assertViewHas('logoArchivo', null)
        ->assertSee('name="ubicacion_oficina"', false)
        ->assertSee('name="logo"', false)
        ->assertDontSee('name="logo_eliminar"', false);
});

it('el formulario de edición muestra los datos guardados, incluido el logo existente', function () {
    Storage::fake('public');

    [$encargado, $idRol] = usuarioConRolParaClientes('encargado', 'encargado_operaciones');
    entrarAlPanelParaClientes($encargado, $idRol);

    $this->post(route('panel.clientes.store'), payloadCliente([
        'ubicacion_oficina' => 'Av. Banzer km 5, Santa Cruz de la Sierra',
        'logo' => logoPngValido(),
    ]));
    $cliente = Cliente::query()->sole();

    $this->get(route('panel.clientes.edit', $cliente))
        ->assertOk()
        ->assertSee('Av. Banzer km 5, Santa Cruz de la Sierra')
        ->assertSee('name="logo_eliminar"', false)
        ->assertViewHas('logoArchivo', fn (?array $logo) => $logo !== null && $logo['nombre'] === basename((string) $cliente->logo_path));
});

it('sube el logo en el alta y lo persiste en el disco público bajo logos/clientes', function () {
    Storage::fake('public');

    [$encargado, $idRol] = usuarioConRolParaClientes('encargado', 'encargado_operaciones');
    entrarAlPanelParaClientes($encargado, $idRol);

    $this->post(route('panel.clientes.store'), payloadCliente(['logo' => logoPngValido()]))
        ->assertRedirect(route('panel.clientes.index'));

    $cliente = Cliente::query()->sole();

    expect($cliente->logo_path)->not->toBeNull()
        ->and($cliente->logo_path)->toStartWith('logos/clientes/');

    Storage::disk('public')->assertExists($cliente->logo_path);
});

it('reemplazar el logo en edición borra el archivo viejo del disco', function () {
    Storage::fake('public');

    [$encargado, $idRol] = usuarioConRolParaClientes('encargado', 'encargado_operaciones');
    entrarAlPanelParaClientes($encargado, $idRol);

    $this->post(route('panel.clientes.store'), payloadCliente(['logo' => logoPngValido('logo-original.png')]));

    $cliente = Cliente::query()->sole();
    $rutaVieja = (string) $cliente->logo_path;
    Storage::disk('public')->assertExists($rutaVieja);

    // El nombre lo genera el servidor con el timestamp del momento (mismo
    // criterio que `GuardarDatosEmpresa`): viajar un segundo hacia adelante
    // evita una colisión de nombre con la carga anterior, igual que en la
    // realidad la edición ocurre en un momento posterior al alta.
    $this->travel(1)->second();

    $this->put(
        route('panel.clientes.update', $cliente),
        payloadCliente(['logo' => logoPngValido('logo-nuevo.png')]),
    )->assertRedirect(route('panel.clientes.index'));

    $cliente->refresh();

    expect($cliente->logo_path)->not->toBeNull()->not->toBe($rutaVieja);
    Storage::disk('public')->assertMissing($rutaVieja);
    Storage::disk('public')->assertExists($cliente->logo_path);
});

it('marcar logo_eliminar sin subir uno nuevo borra el logo del disco y deja logo_path en null', function () {
    Storage::fake('public');

    [$encargado, $idRol] = usuarioConRolParaClientes('encargado', 'encargado_operaciones');
    entrarAlPanelParaClientes($encargado, $idRol);

    $this->post(route('panel.clientes.store'), payloadCliente(['logo' => logoPngValido()]));

    $cliente = Cliente::query()->sole();
    $ruta = (string) $cliente->logo_path;

    $this->put(
        route('panel.clientes.update', $cliente),
        payloadCliente(['logo_eliminar' => '1']),
    )->assertRedirect(route('panel.clientes.index'));

    expect($cliente->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($ruta);
});
