<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Aplicacion\RechazarSesion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\SesionRechazo;
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
 * `GET /panel/sesiones/validacion`, `POST .../validar`, `POST .../rechazar`
 * (HU-14, tarea 14): la cola del jefe de campo. Gateada por
 * `operaciones.sesion.validar` (CA obligatorio: sin el permiso, 403) y por
 * la policy validador≠piloto (invariante 4) por FILA. Mismo patrón que
 * tests/Feature/Operaciones/TrabajosPanelTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolYPersonaParaValidacion(string $username, string $rol, ?PerPersona $persona = null): array
{
    $usuario = SecUser::factory()->create([
        'username' => $username,
        'password' => 'Secreta123',
        'persona_id' => $persona?->id,
    ]);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaValidacion(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function trabajoParaValidacionPanel(): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente panel validación']);
    $campo = Campo::create(['cliente_id' => $cliente->id, 'nombre' => 'Campo panel validación']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-PANELVAL', 'hectareas' => '40.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '40.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '400.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);

    return Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-panelval-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T09:00:00-04:00',
    ]);
}

function sesionParaValidacionPanel(Trabajo $trabajo, PerPersona $piloto, EstadoSesion $estado): Sesion
{
    return Sesion::create([
        'uuid_cliente' => 'uuid-sesion-panelval-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '12.00',
        'estado' => $estado,
        'inicio' => '2026-09-01T09:05:00-04:00',
        'fin' => $estado === EstadoSesion::Abierto ? null : '2026-09-01T10:05:00-04:00',
        'motivo_cierre' => $estado === EstadoSesion::Abierto ? null : 'completado',
        'cierre_uuid_cliente' => $estado === EstadoSesion::Abierto ? null : 'uuid-cierre-'.uniqid(),
    ]);
}

it('la cola muestra solo sesiones cerradas — no abiertas, ni validadas, ni anuladas', function () {
    $trabajo = trabajoParaValidacionPanel();
    $piloto = PerPersona::create(['nombre' => 'Piloto en cola', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $cerrada = sesionParaValidacionPanel($trabajo, $piloto, EstadoSesion::Cerrado);
    sesionParaValidacionPanel($trabajo, $piloto, EstadoSesion::Abierto);
    sesionParaValidacionPanel($trabajo, $piloto, EstadoSesion::Validado);

    // Sigue `cerrado` (invariante 2: el rechazo nunca pisa `estado`), pero
    // ya fue rechazada — la cola tiene que sacarla igual, filtrando por
    // `anulada_en`, no por `estado`.
    $anuladaJefe = PerPersona::create(['nombre' => 'Jefe que ya rechazó', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);
    $anulada = sesionParaValidacionPanel($trabajo, $piloto, EstadoSesion::Cerrado);
    (new RechazarSesion)->ejecutar($anulada, 'ya rechazada antes', $anuladaJefe->id);

    [$jefe, $idRol] = usuarioConRolYPersonaParaValidacion('jefe.cola', 'jefe_campo');
    entrarAlPanelParaValidacion($jefe, $idRol);

    $respuesta = $this->get('/panel/sesiones/validacion')->assertOk();
    $respuesta->assertSee("Sesión #{$cerrada->id}")
        ->assertDontSee("Sesión #{$anulada->id}");
});

it('sin el permiso operaciones.sesion.validar, la pantalla responde 403', function () {
    [$piloto, $idRol] = usuarioConRolYPersonaParaValidacion('piloto.curioso', 'piloto');
    entrarAlPanelParaValidacion($piloto, $idRol);

    $this->get('/panel/sesiones/validacion')->assertForbidden();
});

it('exige sesión de panel para llegar a la pantalla', function () {
    $this->get('/panel/sesiones/validacion')->assertRedirect();
});

it('un jefe que no es el piloto valida la sesión — pasa a validado', function () {
    $trabajo = trabajoParaValidacionPanel();
    $piloto = PerPersona::create(['nombre' => 'Piloto ajeno', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $sesion = sesionParaValidacionPanel($trabajo, $piloto, EstadoSesion::Cerrado);

    $jefePersona = PerPersona::create(['nombre' => 'Jefe persona', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);
    [$jefe, $idRol] = usuarioConRolYPersonaParaValidacion('jefe.valida', 'jefe_campo', $jefePersona);
    entrarAlPanelParaValidacion($jefe, $idRol);

    $this->post("/panel/sesiones/{$sesion->id}/validar")
        ->assertRedirect('/panel/sesiones/validacion');

    expect(Sesion::query()->findOrFail($sesion->id)->estado)->toBe(EstadoSesion::Validado);
});

it('la acción de validar no está disponible cuando el jefe es el piloto de esa fila — el POST forzado da 403', function () {
    $trabajo = trabajoParaValidacionPanel();
    $pilotoQueTambienEsJefe = PerPersona::create(['nombre' => 'Piloto-jefe', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);
    $sesion = sesionParaValidacionPanel($trabajo, $pilotoQueTambienEsJefe, EstadoSesion::Cerrado);

    [$jefe, $idRol] = usuarioConRolYPersonaParaValidacion('jefe.propio', 'jefe_campo', $pilotoQueTambienEsJefe);
    entrarAlPanelParaValidacion($jefe, $idRol);

    // Presentación: el botón de validar no aparece para la fila propia.
    $this->get('/panel/sesiones/validacion')
        ->assertOk()
        ->assertSee(__('operaciones.sesiones_validacion.propia'));

    // Servidor: aunque se fuerce el POST, se rechaza.
    $this->post("/panel/sesiones/{$sesion->id}/validar")->assertForbidden();

    expect(Sesion::query()->findOrFail($sesion->id)->estado)->toBe(EstadoSesion::Cerrado);
});

it('un jefe rechaza una sesión con motivo — se crea la corrección, no un UPDATE', function () {
    $trabajo = trabajoParaValidacionPanel();
    $piloto = PerPersona::create(['nombre' => 'Piloto rechazado', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $sesion = sesionParaValidacionPanel($trabajo, $piloto, EstadoSesion::Cerrado);

    $jefePersona = PerPersona::create(['nombre' => 'Jefe que rechaza', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);
    [$jefe, $idRol] = usuarioConRolYPersonaParaValidacion('jefe.rechaza', 'jefe_campo', $jefePersona);
    entrarAlPanelParaValidacion($jefe, $idRol);

    $this->post("/panel/sesiones/{$sesion->id}/rechazar", ['motivo' => 'Hectáreas no coinciden'])
        ->assertRedirect('/panel/sesiones/validacion');

    $recargada = Sesion::query()->findOrFail($sesion->id);
    expect($recargada->estado)->toBe(EstadoSesion::Cerrado)
        ->and($recargada->anulada_en)->not->toBeNull();

    $rechazo = SesionRechazo::query()->where('anula_a_id', $sesion->id)->sole();
    expect($rechazo->motivo)->toBe('Hectáreas no coinciden')
        ->and($rechazo->rechazado_por)->toBe($jefePersona->id);
});

it('rechazar sin motivo exige el campo — no se aplica sin él', function () {
    $trabajo = trabajoParaValidacionPanel();
    $piloto = PerPersona::create(['nombre' => 'Piloto sin motivo', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $sesion = sesionParaValidacionPanel($trabajo, $piloto, EstadoSesion::Cerrado);

    $jefePersona = PerPersona::create(['nombre' => 'Jefe sin motivo', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);
    [$jefe, $idRol] = usuarioConRolYPersonaParaValidacion('jefe.sinmotivo', 'jefe_campo', $jefePersona);
    entrarAlPanelParaValidacion($jefe, $idRol);

    $this->post("/panel/sesiones/{$sesion->id}/rechazar", [])
        ->assertSessionHasErrors('motivo');

    expect(Sesion::query()->findOrFail($sesion->id)->anulada_en)->toBeNull();
    expect(SesionRechazo::query()->count())->toBe(0);
});

it('publica el ítem de menú de sesiones gateado por operaciones.sesion.validar', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.operacion.items.sesiones')->sole();
    $idPermiso = (int) SecPermission::query()->where('code', 'operaciones.sesion.validar')->value('id');

    expect($itemMenu->ruta)->toBe('panel.sesiones.validacion.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
