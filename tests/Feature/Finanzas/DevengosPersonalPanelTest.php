<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
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
use Illuminate\Support\Carbon;

/*
 * HU-28 (tarea 40): "como piloto o auxiliar, quiero ver mis devengos por
 * período" — primera pantalla del módulo Finanzas. Cubre: un piloto ve sus
 * propios devengos con el total exacto, acceso cruzado -> 404 (sin importar
 * el permiso del actor), filtro por período, 403 sin `finanzas.devengo.ver`,
 * el auxiliar también accede (permiso propio, no solo del piloto), y el
 * ítem de menú gateado.
 *
 * `Carbon::setTestNow()` fija "mes actual" = 2026-09 para que la resolución
 * de período por defecto (controlador y fixtures) sea determinística —
 * sin esto, un devengo generado "ahora" en un test que corre a medianoche
 * podría caer en un mes distinto al que consulta el GET.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Carbon::setTestNow('2026-09-15 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function usuarioConRolParaDevengosPanel(string $username, string $rol, ?int $personaId = null): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123', 'persona_id' => $personaId]);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaDevengosPanel(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function trabajoAbiertoParaDevengosPanel(): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de devengos panel '.uniqid(), 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo de devengos panel']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-DEVPANEL-'.uniqid(), 'hectareas' => '50.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '50.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '500.00',
        'fecha_inicio' => '2026-01-01',
        'estado' => EstadoContrato::Vigente,
    ]);
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-01-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);

    return Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-devpanel-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-01-01T10:00:00-04:00',
    ]);
}

/**
 * Genera un devengo real vía `ValidarSesion` (no un `create()` directo sobre
 * `DevengoPersonal`): es la única vía de la app que produce uno, y `fecha`
 * siempre queda en `Carbon::now()->toDateString()` — el test controla el mes
 * resultante fijando `Carbon::setTestNow()` antes de llamar a esta función.
 */
function generarDevengoParaPanel(PerPersona $piloto, ?PerPersona $auxiliar, PerPersona $jefe, string $hectareas): void
{
    $trabajo = trabajoAbiertoParaDevengosPanel();

    $sesion = Sesion::create([
        'uuid_cliente' => 'uuid-sesion-devpanel-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'auxiliar_id' => $auxiliar?->id,
        'hectareas_declaradas' => $hectareas,
        'estado' => EstadoSesion::Cerrado,
        'inicio' => '2026-01-01T10:05:00-04:00',
        'fin' => '2026-01-01T12:00:00-04:00',
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => 'uuid-cierre-devpanel-'.uniqid(),
    ]);

    (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id);
}

it('un piloto ve sus propios devengos del período, con el total exacto', function () {
    // Mismo caso de decimales que la tarea 16 (hectareas 3.33 × tarifa 12.35
    // → 41.1255 exacto → 41.13 redondeado): confirma que el total de
    // ListarDevengosPersona no arrastra error de redondeo.
    $piloto = PerPersona::create(['nombre' => 'Piloto con devengo propio', 'rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => '12.35', 'activo' => true]);
    $jefe = PerPersona::create(['nombre' => 'Jefe validador de devengos panel', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    generarDevengoParaPanel($piloto, null, $jefe, '3.33');

    [$usuario, $idRol] = usuarioConRolParaDevengosPanel('piloto.propio', 'piloto', $piloto->id);
    entrarAlPanelParaDevengosPanel($usuario, $idRol);

    $this->get(route('panel.devengos.show', $piloto->id))
        ->assertOk()
        ->assertViewHas('devengos', fn ($devengos) => $devengos->count() === 1
            && $devengos->first()->hectareas === '3.33'
            && $devengos->first()->tarifa_ha === '12.35'
            && $devengos->first()->monto === '41.13')
        ->assertViewHas('total', '41.13');
});

it('acceso cruzado: un piloto que pide el devengo de otra persona recibe 404', function () {
    $pilotoA = PerPersona::create(['nombre' => 'Piloto A', 'rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => '100.00', 'activo' => true]);
    $pilotoB = PerPersona::create(['nombre' => 'Piloto B', 'rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => '100.00', 'activo' => true]);

    [$usuarioA, $idRolA] = usuarioConRolParaDevengosPanel('piloto.a', 'piloto', $pilotoA->id);
    entrarAlPanelParaDevengosPanel($usuarioA, $idRolA);

    $this->get(route('panel.devengos.show', $pilotoB->id))->assertNotFound();
});

it('un devengo de otro mes no aparece en el listado del mes actual', function () {
    $piloto = PerPersona::create(['nombre' => 'Piloto con historial', 'rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => '150.00', 'activo' => true]);
    $jefe = PerPersona::create(['nombre' => 'Jefe validador de historial', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    Carbon::setTestNow('2026-01-15 10:00:00');
    generarDevengoParaPanel($piloto, null, $jefe, '10.00');

    Carbon::setTestNow('2026-09-15 10:00:00');
    generarDevengoParaPanel($piloto, null, $jefe, '5.00');

    [$usuario, $idRol] = usuarioConRolParaDevengosPanel('piloto.historial', 'piloto', $piloto->id);
    entrarAlPanelParaDevengosPanel($usuario, $idRol);

    $this->get(route('panel.devengos.show', $piloto->id))
        ->assertOk()
        ->assertViewHas('devengos', fn ($devengos) => $devengos->count() === 1
            && $devengos->first()->hectareas === '5.00');
});

it('un rol sin el permiso finanzas.devengo.ver recibe 403', function () {
    [$jefe, $idRol] = usuarioConRolParaDevengosPanel('jefe.sin.devengo', 'jefe_campo');
    entrarAlPanelParaDevengosPanel($jefe, $idRol);

    $this->get(route('panel.devengos.index'))->assertForbidden();
    $this->get(route('panel.devengos.show', 1))->assertForbidden();
});

it('un auxiliar también puede acceder a sus propios devengos', function () {
    // El permiso quedó asignado en PERMISOS_AUXILIAR, no solo en
    // PERMISOS_PILOTO — este test falla si SeguridadSeeder solo lo hubiera
    // dado al piloto.
    $pilotoDeLaSesion = PerPersona::create(['nombre' => 'Piloto de sesión con auxiliar', 'rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => '100.00', 'activo' => true]);
    $auxiliar = PerPersona::create(['nombre' => 'Auxiliar con devengo propio', 'rol' => RolOperativoPersona::Auxiliar, 'tarifa_ha' => '80.00', 'activo' => true]);
    $jefe = PerPersona::create(['nombre' => 'Jefe validador de auxiliar', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    generarDevengoParaPanel($pilotoDeLaSesion, $auxiliar, $jefe, '10.00');

    [$usuario, $idRol] = usuarioConRolParaDevengosPanel('auxiliar.propio', 'auxiliar', $auxiliar->id);
    entrarAlPanelParaDevengosPanel($usuario, $idRol);

    $this->get(route('panel.devengos.show', $auxiliar->id))
        ->assertOk()
        ->assertViewHas('devengos', fn ($devengos) => $devengos->count() === 1
            && (int) $devengos->first()->persona_id === $auxiliar->id);
});

it('index redirige al show de la persona propia', function () {
    $piloto = PerPersona::create(['nombre' => 'Piloto que entra por index', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    [$usuario, $idRol] = usuarioConRolParaDevengosPanel('piloto.index', 'piloto', $piloto->id);
    entrarAlPanelParaDevengosPanel($usuario, $idRol);

    $this->get(route('panel.devengos.index'))
        ->assertRedirect(route('panel.devengos.show', $piloto->id));
});

it('una cuenta con permiso pero sin persona operativa asociada recibe 404 en index', function () {
    [$usuario, $idRol] = usuarioConRolParaDevengosPanel('piloto.sin.persona', 'piloto', null);
    entrarAlPanelParaDevengosPanel($usuario, $idRol);

    $this->get(route('panel.devengos.index'))->assertNotFound();
});

it('publica el ítem de menú de devengos gateado por finanzas.devengo.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.financiero.items.devengos')->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'finanzas.devengo.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.devengos.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
