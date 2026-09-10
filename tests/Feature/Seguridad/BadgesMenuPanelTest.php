<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Dominio\CausaPausa;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Pausa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/*
 * TE-14 (tarea 60): los badges del menú lateral dejan de ser el mock
 * la maqueta del sidebar y pasan a contadores reales por módulo,
 * compuestos en `CascaraPanel::menuBadges()`. Cubre: los tres ítems sin dato
 * real (programación, reportes al cliente, drones en taller) no aparecen; y
 * cada badge que queda cambia cuando cambia su dato de origen — nunca un
 * valor fijo.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Carbon::setTestNow('2026-09-15 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function usuarioConRolParaBadgesPanel(string $username, string $rol, ?int $personaId = null): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123', 'persona_id' => $personaId]);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaBadgesPanel(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/**
 * `menuBadges` viaja en la cáscara de CUALQUIER página del panel
 * (`CascaraPanel::para()`), no solo el dashboard — necesario desde la tarea
 * 62 (fuga 2), que gatea el dashboard con `seguridad.dashboard.ver` y un
 * `piloto` no lo tiene: sus tests pasan `panel.devengos.show` + su propia
 * persona en vez del default.
 *
 * @param  array<string|int, mixed>  $parametrosRuta
 */
function menuBadgesDelDashboard(string $ruta = 'panel.dashboard', array $parametrosRuta = []): array
{
    $respuesta = test()->get(route($ruta, $parametrosRuta))->assertOk();

    return $respuesta->viewData('menuBadges');
}

function ordenParaBadgesPanel(string $sufijo, EstadoOrdenAplicacion $estado): OrdenAplicacion
{
    $cliente = Cliente::create(['razon_social' => "Cliente badges panel {$sufijo}", 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => "Campo badges panel {$sufijo}"]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => "L-BDG-{$sufijo}", 'hectareas' => '20.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '20.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '200.00',
        'fecha_inicio' => '2026-01-01',
        'estado' => EstadoContrato::Vigente,
    ]);

    return OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-01-01',
        'estado' => $estado,
    ]);
}

function trabajoParaBadgesPanel(string $sufijo): Trabajo
{
    $orden = ordenParaBadgesPanel($sufijo, EstadoOrdenAplicacion::Vigente);

    return Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-bdg-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T08:00:00-04:00',
    ]);
}

function sesionCerradaParaBadgesPanel(string $sufijo): Sesion
{
    $trabajo = trabajoParaBadgesPanel($sufijo);
    $piloto = PerPersona::create(['nombre' => "Piloto badges {$sufijo}", 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    return Sesion::create([
        'uuid_cliente' => "uuid-sesion-bdg-{$sufijo}",
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '10.00',
        'estado' => EstadoSesion::Cerrado,
        'inicio' => '2026-09-01T08:05:00-04:00',
        'fin' => '2026-09-01T09:05:00-04:00',
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => "uuid-cierre-bdg-{$sufijo}",
    ]);
}

it('los tres badges sin dato real no aparecen en el menú', function () {
    [$usuario, $idRol] = usuarioConRolParaBadgesPanel('jefe.badges.sindatos', 'jefe_campo');
    entrarAlPanelParaBadgesPanel($usuario, $idRol);

    $badges = menuBadgesDelDashboard();

    expect($badges)->not->toHaveKey('menu.operacion.items.tablero')
        ->not->toHaveKey('menu.comercial.items.reportes_cliente')
        ->not->toHaveKey('menu.recursos.items.drones');
});

it('el badge de órdenes cambia cuando cambia la cantidad de órdenes vigentes', function () {
    [$usuario, $idRol] = usuarioConRolParaBadgesPanel('jefe.badges.ordenes', 'jefe_campo');
    entrarAlPanelParaBadgesPanel($usuario, $idRol);

    ordenParaBadgesPanel('base', EstadoOrdenAplicacion::Vigente);
    $antes = (int) menuBadgesDelDashboard()['menu.operacion.items.ordenes']['numero'];

    ordenParaBadgesPanel('nueva', EstadoOrdenAplicacion::Vigente);
    $despues = (int) menuBadgesDelDashboard()['menu.operacion.items.ordenes']['numero'];

    expect($despues)->toBe($antes + 1);
});

it('el badge de sesiones cambia cuando cambia la cola de validación pendiente', function () {
    [$usuario, $idRol] = usuarioConRolParaBadgesPanel('jefe.badges.sesiones', 'jefe_campo');
    entrarAlPanelParaBadgesPanel($usuario, $idRol);

    sesionCerradaParaBadgesPanel('base');
    $antes = (int) menuBadgesDelDashboard()['menu.operacion.items.sesiones']['numero'];

    sesionCerradaParaBadgesPanel('nueva');
    $despues = (int) menuBadgesDelDashboard()['menu.operacion.items.sesiones']['numero'];

    expect($despues)->toBe($antes + 1);
});

it('el badge de pausas cambia cuando se registra una pausa nueva en el mes', function () {
    [$usuario, $idRol] = usuarioConRolParaBadgesPanel('jefe.badges.pausas', 'jefe_campo');
    entrarAlPanelParaBadgesPanel($usuario, $idRol);

    $sesion = sesionCerradaParaBadgesPanel('con-pausas');
    $antes = (int) menuBadgesDelDashboard()['menu.operacion.items.pausas']['numero'];

    Pausa::create([
        'sesion_id' => $sesion->id,
        'causa' => CausaPausa::Clima,
        'inicio' => '2026-09-14T09:00:00-04:00',
        'fin' => '2026-09-14T09:20:00-04:00',
        'duracion_minutos' => 20,
    ]);

    $despues = (int) menuBadgesDelDashboard()['menu.operacion.items.pausas']['numero'];

    expect($despues)->toBe($antes + 1);
});

it('el badge de stock cambia cuando una fila cae bajo el mínimo', function () {
    [$usuario, $idRol] = usuarioConRolParaBadgesPanel('jefe.badges.stock', 'jefe_campo');
    entrarAlPanelParaBadgesPanel($usuario, $idRol);

    $antes = (int) menuBadgesDelDashboard()['menu.mantenimiento.items.stock']['numero'];

    $repuesto = Repuesto::create(['codigo' => 'REP-BDG-1', 'descripcion' => 'Repuesto badges', 'unidad' => 'unidad']);
    $base = PerBase::create(['nombre' => 'Base badges']);
    Stock::create(['repuesto_id' => $repuesto->id, 'base_id' => $base->id, 'cantidad' => '1.00', 'stock_minimo' => '10.00']);

    $despues = (int) menuBadgesDelDashboard()['menu.mantenimiento.items.stock']['numero'];

    expect($despues)->toBe($antes + 1);
});

it('el badge de devengos muestra lo propio del usuario y cambia cuando se genera un devengo nuevo', function () {
    $piloto = PerPersona::create(['nombre' => 'Piloto badges devengos', 'rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => '100.00', 'activo' => true]);
    $jefe = PerPersona::create(['nombre' => 'Jefe badges devengos', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    [$usuario, $idRol] = usuarioConRolParaBadgesPanel('piloto.badges.devengos', 'piloto', $piloto->id);
    entrarAlPanelParaBadgesPanel($usuario, $idRol);

    // Piloto no tiene seguridad.dashboard.ver (tarea 62, fuga 2): su única
    // pantalla propia es Financiero > Devengos, la suya (`persona_id`).
    expect(menuBadgesDelDashboard('panel.devengos.show', [$piloto->id])['menu.financiero.items.devengos']['numero'])->toBe('0');

    $trabajo = trabajoParaBadgesPanel('devengo-uno');
    $sesion = Sesion::create([
        'uuid_cliente' => 'uuid-sesion-bdg-devengo-uno',
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '10.00',
        'estado' => EstadoSesion::Cerrado,
        'inicio' => '2026-09-01T08:05:00-04:00',
        'fin' => '2026-09-01T09:05:00-04:00',
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => 'uuid-cierre-bdg-devengo-uno',
    ]);
    (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id);

    expect(menuBadgesDelDashboard('panel.devengos.show', [$piloto->id])['menu.financiero.items.devengos']['numero'])->toBe('1.000');
});

it('un usuario de panel sin persona operativa no tiene badge de devengos', function () {
    [$usuario, $idRol] = usuarioConRolParaBadgesPanel('jefe.badges.sinpersona', 'jefe_campo', null);
    entrarAlPanelParaBadgesPanel($usuario, $idRol);

    expect(menuBadgesDelDashboard())->not->toHaveKey('menu.financiero.items.devengos');
});
