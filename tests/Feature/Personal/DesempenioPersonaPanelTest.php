<?php

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\SesionRechazo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Ficha de desempeño de una persona (HU-58, tarea 81): "¿qué hizo esta
 * persona esta campaña?" — por SESIÓN, nunca por equipo de trabajo (ADR
 * 0015 punto 3). Permiso propio `personal.persona.desempenio`, separado de
 * `.ver` (información sensible, no la ve cualquiera). Fixtures directo por
 * Eloquent, mismo criterio que
 * tests/Feature/Operaciones/LecturaDesempenioPersonaEloquentTest.php (etapa
 * 1 de esta misma tarea), que ya cubrió el contrato de lectura en
 * aislamiento — acá se cubre el caso de uso + la pantalla de punta a punta.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaDesempenio(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaDesempenio(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Cliente (o uno ya existente, para dos campañas del mismo cliente) con una campaña abierta y un contrato asignado a ella. */
function clienteConCampaniaFicha(string $sufijo, string $campaniaCodigo, string $campaniaDesde, string $campaniaHasta, ?Cliente $cliente = null): Contrato
{
    $cliente ??= Cliente::create(['razon_social' => "Cliente ficha {$sufijo}", 'tipo_persona' => 'juridica']);
    $campania = Campania::create([
        'cliente_id' => $cliente->id,
        'codigo' => $campaniaCodigo,
        'fecha_inicio' => $campaniaDesde,
        'fecha_fin' => $campaniaHasta,
        'estado' => EstadoCampania::Abierta,
    ]);

    return Contrato::create([
        'cliente_id' => $cliente->id,
        'campania_id' => $campania->id,
        'hectareas_contratadas' => '100.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '1000.00',
        'fecha_inicio' => $campaniaDesde,
        'estado' => EstadoContrato::Vigente,
    ]);
}

function trabajoDeContratoFicha(Contrato $contrato, string $sufijo): Trabajo
{
    $propiedad = Propiedad::create(['cliente_id' => $contrato->cliente_id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => "Campo ficha {$sufijo}"]);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => "L-FICHA-{$sufijo}", 'hectareas' => '50.00']);
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-08-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => '50.00']);

    return Trabajo::create([
        'uuid_cliente' => "uuid-trabajo-ficha-{$sufijo}",
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'hectareas_declaradas' => '0.00',
        'estado' => EstadoTrabajo::Cerrado,
        'inicio' => '2026-08-05T08:00:00-04:00',
        'fin' => '2026-08-05T12:00:00-04:00',
    ]);
}

function sesionFicha(
    Trabajo $trabajo,
    string $sufijo,
    int $pilotoId,
    ?int $auxiliarId,
    EstadoSesion $estado,
    string $hectareas,
    string $inicio,
    ?string $anuladaEn = null,
): Sesion {
    return Sesion::create([
        'uuid_cliente' => "uuid-sesion-ficha-{$sufijo}",
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $pilotoId,
        'auxiliar_id' => $auxiliarId,
        'hectareas_declaradas' => $hectareas,
        'estado' => $estado,
        'inicio' => $inicio,
        'fin' => null,
        'motivo_cierre' => $estado === EstadoSesion::Abierto ? null : 'completado',
        'anulada_en' => $anuladaEn,
    ]);
}

it('un usuario sin personal.persona.desempenio recibe 403', function () {
    [$jefe, $idRol] = usuarioConRolParaDesempenio('jefe.campo', 'jefe_campo');
    entrarAlPanelParaDesempenio($jefe, $idRol);

    $persona = PerPersona::query()->create(['nombre' => 'Piloto Cualquiera', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $this->get(route('panel.personas.desempenio', $persona))->assertForbidden();
});

it('no deja ver el desempeño a quien tiene el permiso en otro rol pero no en el activo', function () {
    [$multirol, $idEncargado] = usuarioConRolParaDesempenio('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    $persona = PerPersona::query()->create(['nombre' => 'Piloto Cualquiera', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    entrarAlPanelParaDesempenio($multirol, $idPiloto);
    $this->get(route('panel.personas.desempenio', $persona))->assertForbidden();

    entrarAlPanelParaDesempenio($multirol, $idEncargado);
    $this->get(route('panel.personas.desempenio', $persona))->assertOk();
});

it('una persona que voló en dos clientes distintos ve las dos, y filtrando por uno ve solo ese', function () {
    [$encargado, $idRol] = usuarioConRolParaDesempenio('encargado', 'encargado_operaciones');
    entrarAlPanelParaDesempenio($encargado, $idRol);

    $piloto = PerPersona::query()->create(['nombre' => 'Piloto Multicliente', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $contratoA = clienteConCampaniaFicha('A', '2026-A', '2026-01-01', '2026-12-31');
    $trabajoA = trabajoDeContratoFicha($contratoA, 'A');
    sesionFicha($trabajoA, 'A', $piloto->id, null, EstadoSesion::Validado, '12.00', '2026-03-01T08:00:00-04:00');

    $contratoB = clienteConCampaniaFicha('B', '2026-B', '2026-01-01', '2026-12-31');
    $trabajoB = trabajoDeContratoFicha($contratoB, 'B');
    sesionFicha($trabajoB, 'B', $piloto->id, null, EstadoSesion::Cerrado, '8.00', '2026-03-05T08:00:00-04:00');

    $clienteA = Cliente::find($contratoA->cliente_id);
    $clienteB = Cliente::find($contratoB->cliente_id);

    $sinFiltro = $this->get(route('panel.personas.desempenio', [
        'persona' => $piloto,
        'desde' => '2026-01-01',
        'hasta' => '2026-12-31',
    ]))->assertOk();

    // Los códigos de lote son la marca inequívoca de "esta fila pertenece a
    // este cliente": el nombre del cliente también aparece en el <option>
    // del select de filtro (que a propósito sigue listando TODOS los
    // clientes, filtrados o no, para poder cambiar de filtro), así que no
    // sirve para distinguir "aparece en la tabla" de "aparece en el select".
    $sinFiltro->assertSee($clienteA->razon_social)
        ->assertSee($clienteB->razon_social)
        ->assertSee('L-FICHA-A')
        ->assertSee('L-FICHA-B')
        ->assertSee('20,00'); // 12.00 + 8.00, sin decimales de más (invariante 6).

    $conFiltro = $this->get(route('panel.personas.desempenio', [
        'persona' => $piloto,
        'desde' => '2026-01-01',
        'hasta' => '2026-12-31',
        'cliente_id' => $contratoA->cliente_id,
    ]))->assertOk();

    $conFiltro->assertSee('L-FICHA-A')
        ->assertDontSee('L-FICHA-B')
        ->assertSee('12,00');
});

it('una sesión rechazada aparece en la lista de rechazos con su motivo y no suma en las hectáreas aplicadas', function () {
    [$encargado, $idRol] = usuarioConRolParaDesempenio('encargado', 'encargado_operaciones');
    entrarAlPanelParaDesempenio($encargado, $idRol);

    $piloto = PerPersona::query()->create(['nombre' => 'Piloto Con Rechazo', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $jefeCampo = PerPersona::query()->create(['nombre' => 'Jefe Que Rechaza', 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    $contrato = clienteConCampaniaFicha('RECHAZO', '2026-RECHAZO', '2026-01-01', '2026-12-31');
    $trabajo = trabajoDeContratoFicha($contrato, 'RECHAZO');

    sesionFicha($trabajo, 'RECHAZO-VALIDA', $piloto->id, null, EstadoSesion::Validado, '10.00', '2026-03-01T08:00:00-04:00');
    $sesionRechazada = sesionFicha(
        $trabajo,
        'RECHAZO-MALA',
        $piloto->id,
        null,
        EstadoSesion::Cerrado,
        '99.99',
        '2026-03-02T08:00:00-04:00',
        anuladaEn: '2026-03-03T09:00:00-04:00',
    );

    SesionRechazo::create([
        'anula_a_id' => $sesionRechazada->id,
        'motivo' => 'Hectáreas declaradas no coinciden con la captura de RC',
        'rechazado_por' => $jefeCampo->id,
    ]);

    $respuesta = $this->get(route('panel.personas.desempenio', [
        'persona' => $piloto,
        'desde' => '2026-01-01',
        'hasta' => '2026-12-31',
    ]))->assertOk();

    // 99.99 SÍ aparece en la tarjeta de rechazo (es un hecho de esa sesión,
    // visible como contexto) — lo que no puede pasar es que el TOTAL de
    // hectáreas aplicadas las sume: 109,99 (10.00 + 99.99) no debe existir
    // en la página bajo ninguna forma.
    $respuesta->assertSee('Hectáreas declaradas no coinciden con la captura de RC')
        ->assertSee('Jefe Que Rechaza')
        ->assertSee('10,00')
        ->assertDontSee('109,99');
});

it('la campaña que se muestra es la del contrato de esa orden, aunque el cliente tenga dos campañas abiertas que contienen la fecha del vuelo', function () {
    [$encargado, $idRol] = usuarioConRolParaDesempenio('encargado', 'encargado_operaciones');
    entrarAlPanelParaDesempenio($encargado, $idRol);

    $piloto = PerPersona::query()->create(['nombre' => 'Piloto Ambiguo', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    // El contrato de la sesión pertenece a esta campaña...
    $contratoUsado = clienteConCampaniaFicha('AMBIGUA-1', '2025-2026', '2025-07-01', '2026-06-30');
    $cliente = Cliente::find($contratoUsado->cliente_id);

    // ...pero el cliente tiene OTRA campaña abierta cuyo rango también
    // contiene la fecha del vuelo — si el sistema dedujera por fecha,
    // ambigüedad. La ruta correcta es orden → contrato → campania_id.
    clienteConCampaniaFicha('AMBIGUA-2', '2025-Verano', '2025-11-01', '2026-03-31', $cliente);

    $trabajo = trabajoDeContratoFicha($contratoUsado, 'AMBIGUA');
    sesionFicha($trabajo, 'AMBIGUA', $piloto->id, null, EstadoSesion::Validado, '15.00', '2026-01-15T08:00:00-04:00');

    $respuesta = $this->get(route('panel.personas.desempenio', [
        'persona' => $piloto,
        'desde' => '2026-01-01',
        'hasta' => '2026-12-31',
    ]))->assertOk();

    $respuesta->assertSee('2025-2026')
        ->assertDontSee('2025-Verano');
});

it('una persona sin sesiones en el rango ve el estado vacío ilustrado, sin totales ni secciones', function () {
    [$encargado, $idRol] = usuarioConRolParaDesempenio('encargado', 'encargado_operaciones');
    entrarAlPanelParaDesempenio($encargado, $idRol);

    $piloto = PerPersona::query()->create(['nombre' => 'Piloto Sin Vuelos', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $respuesta = $this->get(route('panel.personas.desempenio', [
        'persona' => $piloto,
        'desde' => '2026-01-01',
        'hasta' => '2026-12-31',
    ]))->assertOk();

    // 'Sesiones' a secas no sirve para assertDontSee: también es la etiqueta
    // de un ítem de menú de Operaciones en el sidebar. La clase BEM de la
    // sección sí es exclusiva de esta pantalla.
    $respuesta->assertSee(__('personal.desempenio.vacio_titulo'))
        ->assertSee(__('personal.desempenio.vacio_detalle'))
        ->assertDontSee(__('personal.desempenio.total_hectareas'))
        ->assertDontSee('ag-persona-desempeno__totales')
        ->assertDontSee('ag-persona-desempeno__tabla');
});
