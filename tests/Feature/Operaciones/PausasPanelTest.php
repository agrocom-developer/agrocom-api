<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Dominio\CausaPausa;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Pausa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * `GET/POST /panel/pausas*` (HU-44, tarea 58): "como jefe de campo, quiero
 * registrar las pausas con su causa atribuible (DS-01), para saber qué
 * tiempo se pierde y por qué". CA esencial: pausa ligada a sesión con causa
 * de catálogo; agregado por causa en el tablero. Mismo patrón que
 * tests/Feature/Finanzas/GastosPanelTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaPausas(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaPausas(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function jefeCampoEntraAlPanelParaPausas(): SecUser
{
    [$jefe, $idRol] = usuarioConRolParaPausas('jefe.pausas', 'jefe_campo');
    entrarAlPanelParaPausas($jefe, $idRol);

    return $jefe;
}

function sesionParaPausasPanel(): Sesion
{
    $cliente = Cliente::create(['razon_social' => 'Cliente pausas panel '.uniqid(), 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo pausas panel']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-PAUP-'.uniqid(), 'hectareas' => '60.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '60.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '600.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => '60.00']);
    $trabajo = Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-pausaspanel-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T08:00:00-04:00',
    ]);
    $piloto = PerPersona::create(['nombre' => 'Piloto pausas panel '.uniqid(), 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    return Sesion::create([
        'uuid_cliente' => 'uuid-sesion-pausaspanel-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '10.00',
        'estado' => EstadoSesion::Abierto,
        'inicio' => '2026-09-01T08:05:00-04:00',
    ]);
}

/** Payload mínimo válido de alta. */
function payloadPausa(int $sesionId, array $overrides = []): array
{
    return array_merge([
        'sesion_id' => $sesionId,
        'causa' => CausaPausa::FallaEquipo->value,
        'inicio' => '2026-09-20T09:00:00',
        'fin' => '2026-09-20T09:35:00',
    ], $overrides);
}

it('registra una pausa válida que liga el sesion_id correcto', function () {
    $sesion = sesionParaPausasPanel();
    jefeCampoEntraAlPanelParaPausas();

    $this->post(route('panel.pausas.store'), payloadPausa($sesion->id))
        ->assertRedirect(route('panel.pausas.index'));

    $pausa = Pausa::query()->sole();
    expect($pausa->sesion_id)->toBe($sesion->id)
        ->and($pausa->causa)->toBe(CausaPausa::FallaEquipo)
        ->and($pausa->duracion_minutos)->toBe(35);
});

it('rechaza una causa fuera del catálogo', function () {
    $sesion = sesionParaPausasPanel();
    jefeCampoEntraAlPanelParaPausas();

    $this->post(route('panel.pausas.store'), payloadPausa($sesion->id, ['causa' => 'causa_inventada']))
        ->assertRedirect()
        ->assertSessionHasErrors('causa');

    expect(Pausa::query()->count())->toBe(0);
});

it('rechaza una sesión inexistente', function () {
    jefeCampoEntraAlPanelParaPausas();

    $this->post(route('panel.pausas.store'), payloadPausa(999999))
        ->assertRedirect()
        ->assertSessionHasErrors('sesion_id');

    expect(Pausa::query()->count())->toBe(0);
});

it('muestra un error de negocio, sin crear la fila, cuando el fin es anterior al inicio', function () {
    $sesion = sesionParaPausasPanel();
    jefeCampoEntraAlPanelParaPausas();

    $this->post(route('panel.pausas.store'), payloadPausa($sesion->id, [
        'inicio' => '2026-09-20T09:00:00',
        'fin' => '2026-09-20T08:00:00',
    ]))
        ->assertRedirect()
        ->assertSessionHasErrors('estado');

    expect(Pausa::query()->count())->toBe(0);
});

it('un rol sin los permisos correspondientes recibe 403 en todas las acciones', function () {
    $sesion = sesionParaPausasPanel();

    [$curioso, $idRol] = usuarioConRolParaPausas('piloto.curioso.pausas', 'piloto');
    entrarAlPanelParaPausas($curioso, $idRol);

    $this->get(route('panel.pausas.index'))->assertForbidden();
    $this->get(route('panel.pausas.create'))->assertForbidden();
    $this->post(route('panel.pausas.store'), payloadPausa($sesion->id))->assertForbidden();

    expect(Pausa::query()->count())->toBe(0);
});

it('el agregado por causa del tablero suma exacto, filtrado por período', function () {
    $sesion = sesionParaPausasPanel();
    jefeCampoEntraAlPanelParaPausas();

    $this->post(route('panel.pausas.store'), payloadPausa($sesion->id, [
        'causa' => CausaPausa::Clima->value,
        'inicio' => '2026-09-05T08:00:00',
        'fin' => '2026-09-05T08:40:00',
    ]));
    $this->post(route('panel.pausas.store'), payloadPausa($sesion->id, [
        'causa' => CausaPausa::Clima->value,
        'inicio' => '2026-09-10T08:00:00',
        'fin' => '2026-09-10T08:20:00',
    ]));
    $this->post(route('panel.pausas.store'), payloadPausa($sesion->id, [
        'causa' => CausaPausa::FallaEquipo->value,
        'inicio' => '2026-09-12T08:00:00',
        'fin' => '2026-09-12T09:10:00',
    ]));
    // Fuera del período que se va a filtrar (agosto): no debe sumar.
    $this->post(route('panel.pausas.store'), payloadPausa($sesion->id, [
        'causa' => CausaPausa::Clima->value,
        'inicio' => '2026-08-01T08:00:00',
        'fin' => '2026-08-01T09:00:00',
    ]));

    $respuesta = $this->get(route('panel.pausas.index', ['periodo' => '2026-09']))->assertOk();

    $respuesta->assertSee(__('operaciones.pausas.duracion_valor', ['horas' => 1, 'minutos' => 0]));
    $respuesta->assertSee(__('operaciones.pausas.duracion_valor', ['horas' => 1, 'minutos' => 10]));
    $respuesta->assertSee(__('operaciones.pausas.duracion_valor', ['horas' => 2, 'minutos' => 10]));
});

it('publica el ítem de menú de pausas gateado por operaciones.pausa.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.operacion.items.pausas')->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'operaciones.pausa.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.pausas.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
