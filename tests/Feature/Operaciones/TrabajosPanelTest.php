<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
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

/*
 * `GET /panel/trabajos` (HU-05, tarea 13): pantalla mínima de Operaciones —
 * el jefe ve que algo se cerró. Gateada por `operaciones.trabajo.ver` (CA
 * obligatorio: sin el permiso, 403). Mismo patrón que
 * tests/Feature/Distribucion/VersionesApkPanelTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaTrabajos(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaTrabajos(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function trabajoCerradoDemo(): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente panel trabajos']);
    $campo = Campo::create(['cliente_id' => $cliente->id, 'nombre' => 'Campo panel']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-PANEL', 'hectareas' => '40.00']);
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
    $piloto = PerPersona::create(['nombre' => 'Piloto panel', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $trabajo = Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-panel',
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => '2026-09-01T09:00:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
        'cierre_uuid_cliente' => 'uuid-cierre-trabajo-panel',
    ]);

    Sesion::create([
        'uuid_cliente' => 'uuid-sesion-panel',
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => '12.00',
        'estado' => EstadoSesion::Cerrado,
        'inicio' => '2026-09-01T09:05:00-04:00',
        'fin' => '2026-09-01T10:05:00-04:00',
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => 'uuid-cierre-sesion-panel',
    ]);

    return $trabajo;
}

it('un usuario con el permiso ve el trabajo cerrado en la pantalla', function () {
    trabajoCerradoDemo();
    [$jefe, $idRol] = usuarioConRolParaTrabajos('jefe.campo', 'jefe_campo');

    entrarAlPanelParaTrabajos($jefe, $idRol);

    $this->get('/panel/trabajos')
        ->assertOk()
        ->assertSee(__('operaciones.trabajos.estado.cerrado'));
});

it('sin el permiso operaciones.trabajo.ver, la pantalla responde 403', function () {
    trabajoCerradoDemo();
    [$piloto, $idRol] = usuarioConRolParaTrabajos('piloto.curioso', 'piloto');

    entrarAlPanelParaTrabajos($piloto, $idRol);

    $this->get('/panel/trabajos')->assertForbidden();
});

it('exige sesión de panel para llegar a la pantalla', function () {
    $this->get('/panel/trabajos')->assertRedirect();
});

it('publica el ítem de menú de trabajos gateado por operaciones.trabajo.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.operacion.items.trabajos')->sole();

    $idPermiso = (int) SecPermission::query()->where('code', 'operaciones.trabajo.ver')->value('id');

    expect($itemMenu->ruta)->toBe('panel.trabajos.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
