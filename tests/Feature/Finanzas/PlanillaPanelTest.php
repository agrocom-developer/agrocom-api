<?php

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Anticipo;
use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Planilla;
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
use Brick\Math\BigDecimal;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/*
 * HU-30 (tarea 44): "como dueño, quiero generar la planilla del período
 * desde los devengos y aprobarla, para pagar con un respaldo que cuadre"
 * (espec Sprint 8 §193). Cierra el Sprint 8 (HU-28 devengos, HU-29
 * anticipos, HU-30 planilla).
 *
 * `Carbon::setTestNow()` fija "mes actual" = 2026-09 para que "período"
 * sea determinístico, mismo criterio que `AnticiposPanelTest`. Los devengos
 * de setup se generan vía `ValidarSesion` (única vía real que produce uno),
 * nunca `DevengoPersonal::create()` directo.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
    Carbon::setTestNow('2026-09-15 10:00:00');
    Storage::fake('r2');
});

afterEach(function () {
    Carbon::setTestNow();
});

function usuarioConRolParaPlanilla(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaPlanilla(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function encargadoEntraAlPanelParaPlanilla(): SecUser
{
    [$encargado, $idRol] = usuarioConRolParaPlanilla('encargado.planilla.'.uniqid(), 'encargado_operaciones');
    entrarAlPanelParaPlanilla($encargado, $idRol);

    return $encargado;
}

function duenoEntraAlPanelParaPlanilla(): SecUser
{
    [$dueno, $idRol] = usuarioConRolParaPlanilla('dueno.planilla.'.uniqid(), 'dueno');
    entrarAlPanelParaPlanilla($dueno, $idRol);

    return $dueno;
}

function trabajoAbiertoParaPlanilla(): Trabajo
{
    $cliente = Cliente::create(['razon_social' => 'Cliente de planilla '.uniqid(), 'tipo_persona' => 'juridica']);
    $propiedad = Propiedad::create(['cliente_id' => $cliente->id, 'nombre' => 'Propiedad de prueba '.uniqid()]);
    $lote = Lote::create(['propiedad_id' => $propiedad->id, 'codigo' => 'L-PLA-'.uniqid(), 'hectareas' => '200.00']);
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
        'uuid_cliente' => 'uuid-trabajo-planilla-'.uniqid(),
        'orden_id' => $orden->id,
        'lote_id' => $lote->id,
        'nro_aplicacion' => 1,
        'estado' => EstadoTrabajo::Abierto,
        'inicio' => '2026-09-01T10:00:00-04:00',
    ]);
}

/** Genera un devengo real del mes actual vía `ValidarSesion`. */
function generarDevengoParaPlanilla(PerPersona $piloto, PerPersona $jefe, string $hectareas): void
{
    $trabajo = trabajoAbiertoParaPlanilla();

    $sesion = Sesion::create([
        'uuid_cliente' => 'uuid-sesion-planilla-'.uniqid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'hectareas_declaradas' => $hectareas,
        'estado' => EstadoSesion::Cerrado,
        'inicio' => '2026-09-01T10:05:00-04:00',
        'fin' => '2026-09-01T12:00:00-04:00',
        'motivo_cierre' => 'completado',
        'cierre_uuid_cliente' => 'uuid-cierre-planilla-'.uniqid(),
    ]);

    (new ValidarSesion(new MaquinaEstadosSesion))->ejecutar($sesion, $jefe->id);
}

function personaYJefeParaPlanilla(string $tarifaHa): array
{
    $piloto = PerPersona::create(['nombre' => 'Piloto planilla '.uniqid(), 'rol' => RolOperativoPersona::Piloto, 'tarifa_ha' => $tarifaHa, 'activo' => true]);
    $jefe = PerPersona::create(['nombre' => 'Jefe validador planilla '.uniqid(), 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);

    return [$piloto, $jefe];
}

it('genera la planilla del período con un detalle por persona, neto exacto y el total cuadrado contra los devengos de origen', function () {
    [$piloto1, $jefe] = personaYJefeParaPlanilla('150.00');
    [$piloto2] = personaYJefeParaPlanilla('100.00');

    // Piloto1: devengado = 20 * 150 = 3000.00; anticipo 500.00 => neto 2500.00.
    generarDevengoParaPlanilla($piloto1, $jefe, '20.00');
    // Piloto2: devengado = 10 * 100 = 1000.00; sin anticipos => neto 1000.00.
    generarDevengoParaPlanilla($piloto2, $jefe, '10.00');

    Anticipo::query()->create(['persona_id' => $piloto1->id, 'monto' => '500.00', 'fecha' => '2026-09-10']);

    encargadoEntraAlPanelParaPlanilla();

    $respuesta = $this->post(route('panel.planillas.store'), ['periodo' => '2026-09']);

    $planilla = Planilla::query()->where('periodo', '2026-09')->sole();
    $respuesta->assertRedirect(route('panel.planillas.show', $planilla));

    expect($planilla->estado->value)->toBe('borrador')
        ->and($planilla->detalles()->count())->toBe(2);

    $detallePiloto1 = $planilla->detalles()->where('persona_id', $piloto1->id)->sole();
    expect($detallePiloto1->devengado)->toBe('3000.00')
        ->and($detallePiloto1->anticipos)->toBe('500.00')
        ->and($detallePiloto1->neto)->toBe('2500.00');

    $detallePiloto2 = $planilla->detalles()->where('persona_id', $piloto2->id)->sole();
    expect($detallePiloto2->devengado)->toBe('1000.00')
        ->and($detallePiloto2->anticipos)->toBe('0.00')
        ->and($detallePiloto2->neto)->toBe('1000.00');

    expect($planilla->total)->toBe('3500.00');

    // El total (vía neto) cuadra exacto contra la suma de fin_devengos_personal
    // del período, vista desde las dos tablas — misma cifra a centavo exacto.
    $sumaDetalles = $planilla->detalles->reduce(
        fn (BigDecimal $acumulado, $detalle) => $acumulado->plus($detalle->devengado),
        BigDecimal::of('0.00'),
    );
    $sumaDevengosOrigen = DevengoPersonal::query()
        ->whereBetween('fecha', ['2026-09-01', '2026-09-30'])
        ->get()
        ->reduce(fn (BigDecimal $acumulado, $devengo) => $acumulado->plus($devengo->monto), BigDecimal::of('0.00'));

    expect((string) $sumaDetalles)->toBe((string) $sumaDevengosOrigen)
        ->and((string) $sumaDetalles)->toBe('4000.00');
});

it('generar la misma planilla dos veces es idempotente: no duplica la fila', function () {
    [$piloto, $jefe] = personaYJefeParaPlanilla('150.00');
    generarDevengoParaPlanilla($piloto, $jefe, '20.00');

    encargadoEntraAlPanelParaPlanilla();

    $this->post(route('panel.planillas.store'), ['periodo' => '2026-09']);
    $primera = Planilla::query()->where('periodo', '2026-09')->sole();

    $this->post(route('panel.planillas.store'), ['periodo' => '2026-09']);

    expect(Planilla::query()->where('periodo', '2026-09')->count())->toBe(1);
    expect(Planilla::query()->where('periodo', '2026-09')->sole()->id)->toBe($primera->id);
});

it('aprobar transiciona la planilla a aprobada y genera un PDF por detalle', function () {
    [$piloto1, $jefe] = personaYJefeParaPlanilla('150.00');
    [$piloto2] = personaYJefeParaPlanilla('100.00');
    generarDevengoParaPlanilla($piloto1, $jefe, '20.00');
    generarDevengoParaPlanilla($piloto2, $jefe, '10.00');

    encargadoEntraAlPanelParaPlanilla();
    $this->post(route('panel.planillas.store'), ['periodo' => '2026-09']);
    $planilla = Planilla::query()->where('periodo', '2026-09')->sole();

    $dueno = duenoEntraAlPanelParaPlanilla();

    $this->post(route('panel.planillas.aprobar', $planilla))
        ->assertRedirect(route('panel.planillas.show', $planilla));

    $planillaAprobada = $planilla->fresh();
    expect($planillaAprobada->estado->value)->toBe('aprobada')
        ->and($planillaAprobada->aprobada_por)->toBe($dueno->id)
        ->and($planillaAprobada->aprobada_en)->not->toBeNull();

    foreach ($planillaAprobada->detalles as $detalle) {
        expect($detalle->pdf_path)->not->toBeNull();
        Storage::disk('r2')->assertExists($detalle->pdf_path);
    }

    $detalle = $planillaAprobada->detalles->first();

    $this->get(route('panel.planillas.recibo', [$planillaAprobada, $detalle]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('el recibo de un detalle que no pertenece a la planilla de la ruta responde 404', function () {
    [$pilotoA, $jefe] = personaYJefeParaPlanilla('150.00');
    [$pilotoB] = personaYJefeParaPlanilla('100.00');
    generarDevengoParaPlanilla($pilotoA, $jefe, '20.00');

    encargadoEntraAlPanelParaPlanilla();
    $this->post(route('panel.planillas.store'), ['periodo' => '2026-09']);
    $planillaA = Planilla::query()->where('periodo', '2026-09')->sole();

    Carbon::setTestNow('2026-10-15 10:00:00');
    generarDevengoParaPlanilla($pilotoB, $jefe, '10.00');
    $this->post(route('panel.planillas.store'), ['periodo' => '2026-10']);
    $planillaB = Planilla::query()->where('periodo', '2026-10')->sole();
    Carbon::setTestNow('2026-09-15 10:00:00');

    duenoEntraAlPanelParaPlanilla();
    $this->post(route('panel.planillas.aprobar', $planillaA));
    $this->post(route('panel.planillas.aprobar', $planillaB));

    $detalleDeB = $planillaB->fresh()->detalles->first();

    $this->get(route('panel.planillas.recibo', [$planillaA, $detalleDeB]))
        ->assertNotFound();
});

it('aprobar una planilla ya aprobada falla', function () {
    [$piloto, $jefe] = personaYJefeParaPlanilla('150.00');
    generarDevengoParaPlanilla($piloto, $jefe, '20.00');

    encargadoEntraAlPanelParaPlanilla();
    $this->post(route('panel.planillas.store'), ['periodo' => '2026-09']);
    $planilla = Planilla::query()->where('periodo', '2026-09')->sole();

    duenoEntraAlPanelParaPlanilla();
    $this->post(route('panel.planillas.aprobar', $planilla));

    $respuesta = $this->post(route('panel.planillas.aprobar', $planilla));
    $respuesta->assertRedirect()->assertSessionHasErrors('estado');

    expect($planilla->fresh()->estado->value)->toBe('aprobada');
});

it('solo el rol dueño puede aprobar: un encargado con ver/generar recibe 403', function () {
    [$piloto, $jefe] = personaYJefeParaPlanilla('150.00');
    generarDevengoParaPlanilla($piloto, $jefe, '20.00');

    encargadoEntraAlPanelParaPlanilla();
    $this->post(route('panel.planillas.store'), ['periodo' => '2026-09']);
    $planilla = Planilla::query()->where('periodo', '2026-09')->sole();

    // El MISMO encargado (ver/generar, sin aprobar) intenta aprobar.
    $this->post(route('panel.planillas.aprobar', $planilla))->assertForbidden();

    expect($planilla->fresh()->estado->value)->toBe('borrador');
});

it('sin el permiso finanzas.planilla.ver responde 403 en listado y detalle', function () {
    [$piloto, $jefe] = personaYJefeParaPlanilla('150.00');
    generarDevengoParaPlanilla($piloto, $jefe, '20.00');

    encargadoEntraAlPanelParaPlanilla();
    $this->post(route('panel.planillas.store'), ['periodo' => '2026-09']);
    $planilla = Planilla::query()->where('periodo', '2026-09')->sole();

    [$curioso, $idRol] = usuarioConRolParaPlanilla('piloto.curioso.planilla', 'piloto');
    entrarAlPanelParaPlanilla($curioso, $idRol);

    $this->get(route('panel.planillas.index'))->assertForbidden();
    $this->get(route('panel.planillas.show', $planilla))->assertForbidden();
    $this->post(route('panel.planillas.store'), ['periodo' => '2026-09'])->assertForbidden();
});

it('publica el ítem de menú de planilla gateado por finanzas.planilla.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.financiero.items.planilla')->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'finanzas.planilla.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.planillas.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
