<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Anticipo;
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
 * HU-29 (tarea 41): "como encargado, quiero registrar anticipos validando el
 * tope, para no adelantar más de lo devengado" (espec Sprint 8 §192). Tope:
 * 3.000 Bs/mes Y 70% del devengado del mes, el que sea menor; el rechazo
 * dice cuánto es el máximo disponible.
 *
 * `Carbon::setTestNow()` fija "mes actual" = 2026-09 para que "mes
 * calendario" sea determinístico, mismo criterio que
 * `DevengosPersonalPanelTest`. Los devengos de setup se generan vía
 * `ValidarSesion` (única vía real que produce uno), nunca
 * `DevengoPersonal::create()` directo.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Carbon::setTestNow('2026-09-15 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function usuarioConRolParaAnticipos(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaAnticipos(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function trabajoAbiertoParaAnticipos(): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de anticipos '.uniqid(), 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $campo = Campo::create(['propiedad_id' => $propiedad->id, 'nombre' => 'Campo de anticipos']);
    $lote = Lote::create(['campo_id' => $campo->id, 'codigo' => 'L-ANT-'.uniqid(), 'hectareas' => '200.00']);
    $contrato = Contrato::create([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '200.00',
        'aplicaciones_previstas' => 1,
        'precio_ha' => '10.00',
        'monto_total' => '2000.00',
        'fecha_inicio' => '2026-01-01',
        'estado' => EstadoContrato::Vigente,
    ]);
    $orden = OrdenAplicacion::create([
        'contrato_id' => $contrato->id,
        'nro_aplicacion' => 1,
        'litros_ha' => '10.00',
        'fecha_emision' => '2026-01-01',
        'estado' => EstadoOrdenAplicacion::Vigente,
    ]);
    $orden->ordenLotes()->create(['lote_id' => $lote->id, 'hectareas_solicitadas' => '200.00']);

    return Trabajo::create([
        'uuid_cliente' => 'uuid-trabajo-anticipos-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);
}

/**
 * Genera un devengo real del mes actual (fijado por `Carbon::setTestNow()`)
 * vía `ValidarSesion`, mismo criterio que `generarDevengoParaPanel` de
 * `DevengosPersonalPanelTest`.
 */
function generarDevengoParaAnticipos(PerPersona $piloto, PerPersona $jefe, string $hectareas): void
{
    $trabajo = trabajoAbiertoParaAnticipos();

    $sesion = Sesion::create([
        'uuid_cliente' => 'uuid-sesion-anticipos-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => $hectareas,
        'estado' => EstadoSesion::Cerrado,
        'inicio' => '2026-09-01T10:05:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => 'uuid-cierre-anticipos-'.uniqid(),
    ]);

    (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id);
}

/** Payload mínimo válido de alta. */
function payloadAnticipo(int $personaId, array $overrides = []): array
{
    return array_merge([
        'persona_id' => $personaId,
        'monto' => '500.00',
        'fecha' => '2026-09-20',
        'motivo' => 'Adelanto de sueldo',
    ], $overrides);
}

function personaYJefeParaAnticipos(string $tarifaHa = '150.00'): array
{
    $piloto = PerPersona::create(['nombre' => 'Piloto de anticipos '.uniqid(), 'rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => $tarifaHa, 'activo' => true]);
    $jefe = PerPersona::create(['nombre' => 'Jefe validador de anticipos '.uniqid(), 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    return [$piloto, $jefe];
}

function encargadoEntraAlPanelParaAnticipos(): SecUser
{
    [$encargado, $idRol] = usuarioConRolParaAnticipos('encargado.anticipos', 'encargado_operaciones');
    entrarAlPanelParaAnticipos($encargado, $idRol);

    return $encargado;
}

it('registra un anticipo dentro de ambos topes', function () {
    // hectareas=20 x tarifa=150 => devengado 3000.00; 70% = 2100.00; tope
    // periodo = min(3000, 2100) = 2100.00. 500.00 está muy por debajo.
    [$piloto, $jefe] = personaYJefeParaAnticipos('150.00');
    generarDevengoParaAnticipos($piloto, $jefe, '20.00');

    encargadoEntraAlPanelParaAnticipos();

    $this->post(route('panel.anticipos.store'), payloadAnticipo($piloto->id))
        ->assertRedirect(route('panel.anticipos.index'));

    $anticipo = Anticipo::query()->where('persona_id', $piloto->id)->sole();

    expect($anticipo->monto)->toBe('500.00')
        ->and($anticipo->motivo)->toBe('Adelanto de sueldo');
});

it('rechaza un anticipo que excede el tope absoluto de 3.000 Bs/mes, con el disponible exacto en el mensaje', function () {
    // hectareas=100 x tarifa=100 => devengado 10000.00; 70% = 7000.00; tope
    // periodo = min(3000, 7000) = 3000.00 (lo gana el tope absoluto).
    [$piloto, $jefe] = personaYJefeParaAnticipos('100.00');
    generarDevengoParaAnticipos($piloto, $jefe, '100.00');

    encargadoEntraAlPanelParaAnticipos();

    $this->post(route('panel.anticipos.store'), payloadAnticipo($piloto->id, ['monto' => '3500.00']))
        ->assertRedirect()
        ->assertSessionHasErrors('estado');

    expect(session('errors')->first('estado'))->toContain('3000.00')
        ->and(Anticipo::query()->count())->toBe(0);
});

it('rechaza un anticipo que excede el 70% del devengado, con devengado bajo (tope real menor a 3.000)', function () {
    // hectareas=20 x tarifa=100 => devengado 2000.00; 70% = 1400.00; tope
    // periodo = min(3000, 1400) = 1400.00 (lo gana el 70% del devengado).
    [$piloto, $jefe] = personaYJefeParaAnticipos('100.00');
    generarDevengoParaAnticipos($piloto, $jefe, '20.00');

    encargadoEntraAlPanelParaAnticipos();

    $this->post(route('panel.anticipos.store'), payloadAnticipo($piloto->id, ['monto' => '1500.00']))
        ->assertRedirect()
        ->assertSessionHasErrors('estado');

    expect(session('errors')->first('estado'))->toContain('1400.00')
        ->and(Anticipo::query()->count())->toBe(0);
});

it('dos anticipos del mismo mes: el primero pasa, el segundo se rechaza contra el disponible restante', function () {
    // hectareas=100 x tarifa=100 => devengado 10000.00; tope periodo = 3000.00.
    [$piloto, $jefe] = personaYJefeParaAnticipos('100.00');
    generarDevengoParaAnticipos($piloto, $jefe, '100.00');

    encargadoEntraAlPanelParaAnticipos();

    $this->post(route('panel.anticipos.store'), payloadAnticipo($piloto->id, ['monto' => '2000.00']))
        ->assertRedirect(route('panel.anticipos.index'));

    // Disponible restante: 3000.00 - 2000.00 = 1000.00 — el segundo pedido
    // (1500.00) se rechaza contra ESE remanente, no contra 3000.00 de nuevo.
    $this->post(route('panel.anticipos.store'), payloadAnticipo($piloto->id, ['monto' => '1500.00']))
        ->assertRedirect()
        ->assertSessionHasErrors('estado');

    expect(session('errors')->first('estado'))->toContain('1000.00')
        ->and(Anticipo::query()->count())->toBe(1);

    // Un tercer pedido que sí entra en el remanente (1000.00) se registra.
    $this->post(route('panel.anticipos.store'), payloadAnticipo($piloto->id, ['monto' => '1000.00']))
        ->assertRedirect(route('panel.anticipos.index'));

    expect(Anticipo::query()->count())->toBe(2);
});

it('da de baja un anticipo por soft delete: no aparece en el índice y libera cupo', function () {
    // hectareas=100 x tarifa=100 => devengado 10000.00; tope periodo = 3000.00.
    [$piloto, $jefe] = personaYJefeParaAnticipos('100.00');
    generarDevengoParaAnticipos($piloto, $jefe, '100.00');

    encargadoEntraAlPanelParaAnticipos();

    $this->post(route('panel.anticipos.store'), payloadAnticipo($piloto->id, ['monto' => '2500.00']))
        ->assertRedirect(route('panel.anticipos.index'));

    $anticipo = Anticipo::query()->sole();

    // Con 2500.00 ya adelantados, un segundo pedido de 1000.00 excede el
    // remanente (500.00) — se rechaza.
    $this->post(route('panel.anticipos.store'), payloadAnticipo($piloto->id, ['monto' => '1000.00']))
        ->assertSessionHasErrors('estado');
    expect(Anticipo::query()->count())->toBe(1);

    $this->delete(route('panel.anticipos.destroy', $anticipo))
        ->assertRedirect(route('panel.anticipos.index'));

    $borrado = Anticipo::withTrashed()->findOrFail($anticipo->id);
    expect($borrado->trashed())->toBeTrue();

    $this->get(route('panel.anticipos.index'))
        ->assertOk()
        ->assertDontSee('Bs 2500.00');

    // El anticipo de 1000.00 que antes excedía el remanente ahora entra: el
    // eliminado ya no cuenta en la suma del mes.
    $this->post(route('panel.anticipos.store'), payloadAnticipo($piloto->id, ['monto' => '1000.00']))
        ->assertRedirect(route('panel.anticipos.index'));

    expect(Anticipo::withTrashed()->count())->toBe(2)
        ->and(Anticipo::query()->count())->toBe(1);
});

it('registra en bitácora el alta y la baja de un anticipo', function () {
    [$piloto, $jefe] = personaYJefeParaAnticipos('150.00');
    generarDevengoParaAnticipos($piloto, $jefe, '20.00');

    $encargado = encargadoEntraAlPanelParaAnticipos();

    $this->post(route('panel.anticipos.store'), payloadAnticipo($piloto->id));
    $anticipo = Anticipo::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'fin_anticipos')
        ->where('registro_id', $anticipo->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['monto'])->toBe('500.00');

    $this->delete(route('panel.anticipos.destroy', $anticipo))
        ->assertRedirect(route('panel.anticipos.index'));

    Bitacora::query()
        ->where('tabla', 'fin_anticipos')
        ->where('registro_id', $anticipo->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('un rol sin los permisos correspondientes recibe 403 en todas las acciones', function () {
    [$piloto] = personaYJefeParaAnticipos('150.00');

    [$curioso, $idRol] = usuarioConRolParaAnticipos('piloto.curioso.anticipos', 'piloto');
    entrarAlPanelParaAnticipos($curioso, $idRol);

    $anticipo = Anticipo::query()->create(['persona_id' => $piloto->id, 'monto' => '100.00', 'fecha' => '2026-09-20']);

    $this->get(route('panel.anticipos.index'))->assertForbidden();
    $this->get(route('panel.anticipos.create'))->assertForbidden();
    $this->post(route('panel.anticipos.store'), payloadAnticipo($piloto->id))->assertForbidden();
    $this->delete(route('panel.anticipos.destroy', $anticipo))->assertForbidden();

    expect(Anticipo::query()->count())->toBe(1)
        ->and($anticipo->fresh()?->trashed())->toBeFalse();
});

it('el monto queda exacto en DECIMAL, sin error de redondeo flotante (caso 3.33 × 12.35)', function () {
    // Mismo caso que la tarea 16: hectareas 3.33 × tarifa 12.35 => devengado
    // exacto 41.13 (BigDecimal, nunca float). 70% de 41.13 = 28.791, que
    // redondeado hacia abajo a 2 decimales (HalfDown, a favor del tope) da
    // 28.79 exacto — nunca un residuo tipo "28.790000000000003".
    [$piloto, $jefe] = personaYJefeParaAnticipos('12.35');
    generarDevengoParaAnticipos($piloto, $jefe, '3.33');

    encargadoEntraAlPanelParaAnticipos();

    // Exactamente el disponible: pasa.
    $this->post(route('panel.anticipos.store'), payloadAnticipo($piloto->id, ['monto' => '28.79']))
        ->assertRedirect(route('panel.anticipos.index'));

    $anticipo = Anticipo::query()->sole();
    expect($anticipo->monto)->toBe('28.79');

    // Un centavo más excede el disponible restante (28.79 - 28.79 = 0.00).
    $this->post(route('panel.anticipos.store'), payloadAnticipo($piloto->id, ['monto' => '0.01']))
        ->assertSessionHasErrors('estado');

    expect(session('errors')->first('estado'))->toContain('0.00');
});

it('publica el ítem de menú de anticipos gateado por finanzas.anticipo.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.financiero.items.anticipos')->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'finanzas.anticipo.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.anticipos.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
