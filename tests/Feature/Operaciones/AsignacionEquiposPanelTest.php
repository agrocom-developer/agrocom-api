<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/*
 * `GET/POST /panel/asignacion-equipos*` (HU-70, tarea 85; rediseñado por
 * HU-92, tarea 107 para confirmar N equipos en un solo submit): pantalla
 * propia (no la ficha de `ordenes`, ver docblock de
 * `AsignacionEquiposController`), gateada por un único permiso
 * (`operaciones.orden.asignar_equipos`), que `SeguridadSeeder` le da a
 * `jefe_campo` — el rol que hace el reclamo de negocio — sin darle
 * `operaciones.orden.ver`.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaAsignacionPanel(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaAsignacionPanel(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/**
 * @return array{0: OrdenAplicacion, 1: int} la orden (con UN lote) y el id de ese lote.
 */
function ordenVigenteParaAsignacionPanel(string $hectareasLote, EstadoOrdenAplicacion $estado = EstadoOrdenAplicacion::Vigente): array
{
    $cliente = Cliente::create(['razon_social' => 'Cliente panel asignación '.Str::random(6), 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo panel asignación '.Str::random(6)]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-PNL-'.Str::random(6), 'hectareas' => $hectareasLote]);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => $hectareasLote,
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '400.00',
        'fecha_inicio' => '2026-09-01',
        'estado' => EstadoContrato::Vigente,
    ]);

    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-09-01',
        'estado' => $estado,
    ]);
    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => $hectareasLote]);

    return [$orden->refresh(), $lote->id];
}

function equipoVigenteParaAsignacionPanel(): EquipoTrabajo
{
    $base = PerBase::create(['nombre' => 'Base panel asignación '.Str::random(6)]);

    return EquipoTrabajo::create([
        'codigo' => 'EQ-PNL-'.Str::random(6),
        'base_id' => $base->id,
        'estado' => EstadoEquipoTrabajo::Activo,
        'desde' => now()->subYear()->toDateString(),
        'hasta' => null,
    ]);
}

it('un jefe de campo (sin operaciones.orden.ver) ve el listado de órdenes vigentes con su resumen', function () {
    [$jefe, $idRol] = usuarioConRolParaAsignacionPanel('jefe.campo', 'jefe_campo');
    entrarAlPanelParaAsignacionPanel($jefe, $idRol);

    [$orden] = ordenVigenteParaAsignacionPanel('500.00');

    $this->get(route('panel.asignacion-equipos.index'))
        ->assertOk()
        ->assertSeeText('#'.$orden->nro_aplicacion);

    $this->get(route('panel.ordenes.index'))->assertForbidden();
});

it('asigna un equipo vigente a una orden vigente y lo muestra en la ficha', function () {
    [$jefe, $idRol] = usuarioConRolParaAsignacionPanel('jefe.asigna', 'jefe_campo');
    entrarAlPanelParaAsignacionPanel($jefe, $idRol);

    [$orden, $loteId] = ordenVigenteParaAsignacionPanel('500.00');
    $equipo = equipoVigenteParaAsignacionPanel();

    $this->post(route('panel.asignacion-equipos.store', $orden), [
        'equipos' => [
            ['equipo_trabajo_id' => $equipo->id, 'lotes' => [['lote_id' => $loteId, 'hectareas' => '300.00']]],
        ],
    ])
        ->assertRedirect(route('panel.asignacion-equipos.show', $orden))
        ->assertSessionHas('estado');

    $trabajo = Trabajo::query()->where('orden_id', $orden->id)->sole();
    expect($trabajo->equipo_trabajo_id)->toBe($equipo->id)
        ->and($trabajo->lote_id)->toBe($loteId)
        ->and((string) $trabajo->hectareas_declaradas)->toBe('300.00');

    $this->get(route('panel.asignacion-equipos.show', $orden))
        ->assertOk()
        ->assertSeeText($equipo->codigo);
});

it('confirma en un solo submit el reparto de 2 equipos sobre 2 lotes distintos', function () {
    [$jefe, $idRol] = usuarioConRolParaAsignacionPanel('jefe.bloque', 'jefe_campo');
    entrarAlPanelParaAsignacionPanel($jefe, $idRol);

    [$orden, $loteUno] = ordenVigenteParaAsignacionPanel('300.00');
    $loteDos = Lote::create([
        'campo_id' => Lote::query()->findOrFail($loteUno)->campo_id,
        'codigo' => 'L-PNL-'.Str::random(6),
        'hectareas' => '200.00',
    ]);
    $orden->ordenLotes()->create(['lote_id' => $loteDos->id, 'hectareas_solicitadas' => '200.00']);

    $equipoUno = equipoVigenteParaAsignacionPanel();
    $equipoDos = equipoVigenteParaAsignacionPanel();

    $this->post(route('panel.asignacion-equipos.store', $orden), [
        'equipos' => [
            ['equipo_trabajo_id' => $equipoUno->id, 'lotes' => [['lote_id' => $loteUno, 'hectareas' => '300.00']]],
            ['equipo_trabajo_id' => $equipoDos->id, 'lotes' => [['lote_id' => $loteDos->id, 'hectareas' => '200.00']]],
        ],
    ])
        ->assertRedirect(route('panel.asignacion-equipos.show', $orden))
        ->assertSessionHas('estado');

    expect(Trabajo::query()->where('orden_id', $orden->id)->count())->toBe(2);
});

it('rechaza asignar más hectáreas que las restantes del lote, sin crear el trabajo', function () {
    [$jefe, $idRol] = usuarioConRolParaAsignacionPanel('jefe.excede', 'jefe_campo');
    entrarAlPanelParaAsignacionPanel($jefe, $idRol);

    [$orden, $loteId] = ordenVigenteParaAsignacionPanel('500.00');
    $equipo = equipoVigenteParaAsignacionPanel();

    $this->post(route('panel.asignacion-equipos.store', $orden), [
        'equipos' => [
            ['equipo_trabajo_id' => $equipo->id, 'lotes' => [['lote_id' => $loteId, 'hectareas' => '500.01']]],
        ],
    ])
        ->assertRedirect(route('panel.asignacion-equipos.show', $orden))
        ->assertSessionHasErrors('equipos');

    expect(Trabajo::query()->where('orden_id', $orden->id)->count())->toBe(0);
});

it('rechaza asignar una orden que ya no está vigente', function () {
    [$jefe, $idRol] = usuarioConRolParaAsignacionPanel('jefe.novigente', 'jefe_campo');
    entrarAlPanelParaAsignacionPanel($jefe, $idRol);

    [$orden, $loteId] = ordenVigenteParaAsignacionPanel('500.00', EstadoOrdenAplicacion::Consumida);
    $equipo = equipoVigenteParaAsignacionPanel();

    $this->post(route('panel.asignacion-equipos.store', $orden), [
        'equipos' => [
            ['equipo_trabajo_id' => $equipo->id, 'lotes' => [['lote_id' => $loteId, 'hectareas' => '100.00']]],
        ],
    ])
        ->assertRedirect(route('panel.asignacion-equipos.show', $orden))
        ->assertSessionHasErrors('equipos');

    expect(Trabajo::query()->where('orden_id', $orden->id)->count())->toBe(0);
});

it('el formulario rechaza un equipo_trabajo_id inexistente y hectáreas no numéricas', function () {
    [$jefe, $idRol] = usuarioConRolParaAsignacionPanel('jefe.formulario', 'jefe_campo');
    entrarAlPanelParaAsignacionPanel($jefe, $idRol);

    [$orden, $loteId] = ordenVigenteParaAsignacionPanel('500.00');

    $this->post(route('panel.asignacion-equipos.store', $orden), [
        'equipos' => [
            ['equipo_trabajo_id' => 999999, 'lotes' => [['lote_id' => $loteId, 'hectareas' => 'no-es-un-numero']]],
        ],
    ])->assertSessionHasErrors(['equipos.0.equipo_trabajo_id', 'equipos.0.lotes.0.hectareas']);
});

it('rechaza un lote que no pertenece a la orden', function () {
    [$jefe, $idRol] = usuarioConRolParaAsignacionPanel('jefe.loteajeno', 'jefe_campo');
    entrarAlPanelParaAsignacionPanel($jefe, $idRol);

    [$orden] = ordenVigenteParaAsignacionPanel('500.00');
    [, $loteAjeno] = ordenVigenteParaAsignacionPanel('100.00');
    $equipo = equipoVigenteParaAsignacionPanel();

    $this->post(route('panel.asignacion-equipos.store', $orden), [
        'equipos' => [
            ['equipo_trabajo_id' => $equipo->id, 'lotes' => [['lote_id' => $loteAjeno, 'hectareas' => '50.00']]],
        ],
    ])->assertSessionHasErrors(['equipos.0.lotes.0.lote_id']);

    expect(Trabajo::query()->where('orden_id', $orden->id)->count())->toBe(0);
});

it('un rol sin el permiso recibe 403 en las tres acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaAsignacionPanel('piloto.curioso.asig', 'piloto');
    entrarAlPanelParaAsignacionPanel($piloto, $idRol);

    [$orden, $loteId] = ordenVigenteParaAsignacionPanel('500.00');
    $equipo = equipoVigenteParaAsignacionPanel();

    $this->get(route('panel.asignacion-equipos.index'))->assertForbidden();
    $this->get(route('panel.asignacion-equipos.show', $orden))->assertForbidden();
    $this->post(route('panel.asignacion-equipos.store', $orden), [
        'equipos' => [
            ['equipo_trabajo_id' => $equipo->id, 'lotes' => [['lote_id' => $loteId, 'hectareas' => '100.00']]],
        ],
    ])->assertForbidden();
});
