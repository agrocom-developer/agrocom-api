<?php

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rubro;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
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
 * HU-34 (tarea 48): "como jefe de campo, quiero rendir los gastos que hice
 * en campo; el encargado los aprueba para reponer el fondo" (espec §4.4).
 * Máquina de estados propia `abierta → presentada → aprobada`, sin vuelta
 * atrás. CA esencial: el `monto` es siempre la suma exacta de los
 * `fin_gastos` asociados (invariante 6: DECIMAL, nunca float), recalculada
 * en cada transición; y el aprobador nunca puede ser la misma persona que
 * el `jefe_campo_id` de la rendición (invariante 4), por PERSONA — no por
 * rol ni por permiso (`finanzas.rendicion.aprobar` lo tiene el encargado).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

/**
 * Mismo patrón que `usuarioConRolYPersonaParaValidacion` de
 * `ValidacionSesionesPanelTest`: vincula el `SecUser` a una `PerPersona` vía
 * `persona_id`, para que `AutorizacionPanelWeb::personaId()` pueda resolverla
 * (necesario para que `PoliticaAprobacionRendicion` compare por persona).
 */
function usuarioConRolYPersonaParaRendiciones(string $username, string $rol, ?PerPersona $persona = null): array
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

function entrarAlPanelParaRendiciones(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/**
 * `encargado_operaciones` tiene los cuatro permisos de grano fino
 * (`ver`/`crear`/`presentar`/`aprobar` — `SeguridadSeeder`), así que sirve
 * para toda la parte "autorizada" del flujo. Opcionalmente se lo vincula a
 * `$persona`, para los casos donde importa quién es (p. ej. el aprobador).
 */
function encargadoEntraAlPanelParaRendiciones(?PerPersona $persona = null): SecUser
{
    [$encargado, $idRol] = usuarioConRolYPersonaParaRendiciones('encargado.rendiciones.'.uniqid(), 'encargado_operaciones', $persona);
    entrarAlPanelParaRendiciones($encargado, $idRol);

    return $encargado;
}

function baseParaRendiciones(): PerBase
{
    return PerBase::create(['nombre' => 'Base de rendiciones '.uniqid()]);
}

function jefeCampoParaRendiciones(): PerPersona
{
    return PerPersona::create(['nombre' => 'Jefe de campo '.uniqid(), 'rol' => RolOperativoPersona::JefeCampo, 'activo' => true]);
}

function encargadoPersonaParaRendiciones(): PerPersona
{
    return PerPersona::create(['nombre' => 'Encargado aprobador '.uniqid(), 'rol' => RolOperativoPersona::EncargadoOperaciones, 'activo' => true]);
}

function rubroParaRendiciones(): Rubro
{
    return Rubro::query()->firstOrFail();
}

/** Gasto suelto (sin `rendicion_id`), imputado a `$base`, con `monto` exacto igual a `$monto`. */
function gastoSueltoParaRendiciones(PerBase $base, string $monto): Gasto
{
    return Gasto::query()->create([
        'fecha' => '2026-09-05',
        'rubro_id' => rubroParaRendiciones()->id,
        'cantidad' => '1.00',
        'precio_unitario' => $monto,
        'monto' => $monto,
        'base_id' => $base->id,
    ]);
}

/**
 * Alta real vía HTTP (`POST panel.rendiciones.store`) — exige que ya haya un
 * usuario en el panel con `finanzas.rendicion.crear` (mismo criterio que
 * `generarDevengoParaPlanilla` disparando el caso de uso real en vez de
 * `Model::create()` directo para lo que sí importa probar de punta a punta).
 */
function crearRendicionAbiertaParaRendiciones(PerBase $base, PerPersona $jefeCampo, string $fecha = '2026-09-01', ?string $descripcion = null): Rendicion
{
    test()->post(route('panel.rendiciones.store'), array_filter([
        'base_id' => $base->id,
        'jefe_campo_id' => $jefeCampo->id,
        'fecha' => $fecha,
        'descripcion' => $descripcion,
    ], fn ($valor) => $valor !== null));

    return Rendicion::query()
        ->where('base_id', $base->id)
        ->where('jefe_campo_id', $jefeCampo->id)
        ->latest('id')
        ->firstOrFail();
}

it('transición completa válida abierta → presentada → aprobada, con monto exacto igual a la suma de los gastos asociados', function () {
    $base = baseParaRendiciones();
    $jefeCampo = jefeCampoParaRendiciones();

    encargadoEntraAlPanelParaRendiciones();
    $rendicion = crearRendicionAbiertaParaRendiciones($base, $jefeCampo);

    expect($rendicion->estado->value)->toBe('abierta')
        ->and($rendicion->monto)->toBe('0.00');

    $gasto1 = gastoSueltoParaRendiciones($base, '120.50');
    $gasto2 = gastoSueltoParaRendiciones($base, '30.25');

    $this->post(route('panel.rendiciones.asociar_gasto', [$rendicion, $gasto1]))
        ->assertRedirect(route('panel.rendiciones.show', $rendicion));
    $this->post(route('panel.rendiciones.asociar_gasto', [$rendicion, $gasto2]))
        ->assertRedirect(route('panel.rendiciones.show', $rendicion));

    $this->post(route('panel.rendiciones.presentar', $rendicion))
        ->assertRedirect(route('panel.rendiciones.show', $rendicion));

    $presentada = $rendicion->fresh();
    expect($presentada->estado->value)->toBe('presentada')
        ->and($presentada->monto)->toBe('150.75');

    // Aprobador: persona distinta al jefe de campo que rindió.
    $aprobadorPersona = encargadoPersonaParaRendiciones();
    $aprobador = encargadoEntraAlPanelParaRendiciones($aprobadorPersona);

    $this->post(route('panel.rendiciones.aprobar', $rendicion))
        ->assertRedirect(route('panel.rendiciones.show', $rendicion));

    $aprobada = $rendicion->fresh();
    expect($aprobada->estado->value)->toBe('aprobada')
        ->and($aprobada->monto)->toBe('150.75')
        ->and($aprobada->aprobado_por)->toBe($aprobador->persona_id);
});

it('rechaza presentada → aprobada cuando el aprobador es la misma persona que el jefe de campo que rindió, aunque tenga finanzas.rendicion.aprobar en su rol activo', function () {
    $base = baseParaRendiciones();
    $jefeCampo = jefeCampoParaRendiciones();

    encargadoEntraAlPanelParaRendiciones();
    $rendicion = crearRendicionAbiertaParaRendiciones($base, $jefeCampo);

    $gasto = gastoSueltoParaRendiciones($base, '80.00');
    $this->post(route('panel.rendiciones.asociar_gasto', [$rendicion, $gasto]));
    $this->post(route('panel.rendiciones.presentar', $rendicion));

    expect($rendicion->fresh()->estado->value)->toBe('presentada');

    // La MISMA persona que figura como jefe_campo_id entra con el rol
    // encargado_operaciones (que sí tiene finanzas.rendicion.aprobar) — la
    // policy la frena igual, por PERSONA, no por rol ni por permiso.
    encargadoEntraAlPanelParaRendiciones($jefeCampo);

    $this->post(route('panel.rendiciones.aprobar', $rendicion))
        ->assertForbidden();

    expect($rendicion->fresh()->estado->value)->toBe('presentada');
});

it('rechaza presentar una rendición sin ningún gasto asociado', function () {
    $base = baseParaRendiciones();
    $jefeCampo = jefeCampoParaRendiciones();

    encargadoEntraAlPanelParaRendiciones();
    $rendicion = crearRendicionAbiertaParaRendiciones($base, $jefeCampo);

    $respuesta = $this->post(route('panel.rendiciones.presentar', $rendicion));
    $respuesta->assertRedirect(route('panel.rendiciones.show', $rendicion))
        ->assertSessionHasErrors('estado');

    expect($rendicion->fresh()->estado->value)->toBe('abierta');
});

it('recalcula el monto en la aprobación desde los gastos vivos — sin error de redondeo flotante (33.33 + 33.34 = 66.67) y sin arrastrar uno dado de baja después de presentar', function () {
    $base = baseParaRendiciones();
    $jefeCampo = jefeCampoParaRendiciones();

    encargadoEntraAlPanelParaRendiciones();
    $rendicion = crearRendicionAbiertaParaRendiciones($base, $jefeCampo);

    $gasto1 = gastoSueltoParaRendiciones($base, '33.33');
    $gasto2 = gastoSueltoParaRendiciones($base, '33.34');

    $this->post(route('panel.rendiciones.asociar_gasto', [$rendicion, $gasto1]));
    $this->post(route('panel.rendiciones.asociar_gasto', [$rendicion, $gasto2]));

    $this->post(route('panel.rendiciones.presentar', $rendicion));

    // Nunca "66.66999999999999" — el vicio clásico de sumar floats.
    expect($rendicion->fresh()->monto)->toBe('66.67');

    // Uno de los gastos se da de baja (soft delete) después de presentar.
    $gasto2->delete();

    $aprobadorPersona = encargadoPersonaParaRendiciones();
    encargadoEntraAlPanelParaRendiciones($aprobadorPersona);

    $this->post(route('panel.rendiciones.aprobar', $rendicion));

    // Recalculado en la aprobación desde los gastos vivos, no copiado del
    // valor congelado en la presentación: queda solo el gasto que sigue vivo.
    expect($rendicion->fresh()->monto)->toBe('33.33');
});

it('rechaza una transición inválida: abierta → aprobada directo, sin pasar por presentada', function () {
    $base = baseParaRendiciones();
    $jefeCampo = jefeCampoParaRendiciones();

    encargadoEntraAlPanelParaRendiciones();
    $rendicion = crearRendicionAbiertaParaRendiciones($base, $jefeCampo);

    $aprobadorPersona = encargadoPersonaParaRendiciones();
    encargadoEntraAlPanelParaRendiciones($aprobadorPersona);

    $respuesta = $this->post(route('panel.rendiciones.aprobar', $rendicion));
    $respuesta->assertRedirect(route('panel.rendiciones.show', $rendicion))
        ->assertSessionHasErrors('estado');

    expect($rendicion->fresh()->estado->value)->toBe('abierta');
});

it('un rol sin los permisos correspondientes recibe 403 en todas las acciones', function () {
    $base = baseParaRendiciones();
    $jefeCampo = jefeCampoParaRendiciones();

    encargadoEntraAlPanelParaRendiciones();
    $rendicion = crearRendicionAbiertaParaRendiciones($base, $jefeCampo);
    $gasto = gastoSueltoParaRendiciones($base, '50.00');

    [$curioso, $idRol] = usuarioConRolYPersonaParaRendiciones('piloto.curioso.rendiciones', 'piloto');
    entrarAlPanelParaRendiciones($curioso, $idRol);

    $this->get(route('panel.rendiciones.index'))->assertForbidden();
    $this->get(route('panel.rendiciones.create'))->assertForbidden();
    $this->post(route('panel.rendiciones.store'), [
        'base_id' => $base->id,
        'jefe_campo_id' => $jefeCampo->id,
        'fecha' => '2026-09-01',
    ])->assertForbidden();
    $this->get(route('panel.rendiciones.show', $rendicion))->assertForbidden();
    $this->post(route('panel.rendiciones.asociar_gasto', [$rendicion, $gasto]))->assertForbidden();
    $this->post(route('panel.rendiciones.presentar', $rendicion))->assertForbidden();
    $this->post(route('panel.rendiciones.aprobar', $rendicion))->assertForbidden();

    expect($rendicion->fresh()->estado->value)->toBe('abierta')
        ->and($gasto->fresh()->rendicion_id)->toBeNull();
});

it('registra en bitácora el alta y cada transición de estado de una rendición', function () {
    $base = baseParaRendiciones();
    $jefeCampo = jefeCampoParaRendiciones();

    $encargado = encargadoEntraAlPanelParaRendiciones();
    $rendicion = crearRendicionAbiertaParaRendiciones($base, $jefeCampo);

    $filaAlta = Bitacora::query()
        ->where('tabla', 'fin_rendiciones')
        ->where('registro_id', $rendicion->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaAlta->user_id)->toBe($encargado->id)
        ->and($filaAlta->despues['estado'])->toBe('abierta')
        ->and($filaAlta->despues['monto'])->toBe('0.00');

    $gasto = gastoSueltoParaRendiciones($base, '75.00');
    $this->post(route('panel.rendiciones.asociar_gasto', [$rendicion, $gasto]));
    $this->post(route('panel.rendiciones.presentar', $rendicion));

    $filaPresentada = Bitacora::query()
        ->where('tabla', 'fin_rendiciones')
        ->where('registro_id', $rendicion->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->orderBy('id')
        ->firstOrFail();

    expect($filaPresentada->user_id)->toBe($encargado->id)
        ->and($filaPresentada->despues['estado'])->toBe('presentada')
        ->and($filaPresentada->despues['monto'])->toBe('75.00');

    $aprobadorPersona = encargadoPersonaParaRendiciones();
    $aprobador = encargadoEntraAlPanelParaRendiciones($aprobadorPersona);

    $this->post(route('panel.rendiciones.aprobar', $rendicion));

    $filaAprobada = Bitacora::query()
        ->where('tabla', 'fin_rendiciones')
        ->where('registro_id', $rendicion->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->orderBy('id')
        ->skip(1)
        ->firstOrFail();

    expect($filaAprobada->user_id)->toBe($aprobador->id)
        ->and($filaAprobada->despues['estado'])->toBe('aprobada')
        ->and($filaAprobada->despues['aprobado_por'])->toBe($aprobadorPersona->id);

    expect(Bitacora::query()
        ->where('tabla', 'fin_rendiciones')
        ->where('registro_id', $rendicion->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->count())->toBe(2);
});

it('rechaza asociar un gasto que ya pertenece a otra rendición viva — el gasto sigue apuntando a la original', function () {
    $base = baseParaRendiciones();
    $jefeCampo1 = jefeCampoParaRendiciones();
    $jefeCampo2 = jefeCampoParaRendiciones();

    encargadoEntraAlPanelParaRendiciones();
    $rendicion1 = crearRendicionAbiertaParaRendiciones($base, $jefeCampo1, '2026-09-01');
    $rendicion2 = crearRendicionAbiertaParaRendiciones($base, $jefeCampo2, '2026-09-02');

    $gasto = gastoSueltoParaRendiciones($base, '40.00');

    $this->post(route('panel.rendiciones.asociar_gasto', [$rendicion1, $gasto]))
        ->assertRedirect(route('panel.rendiciones.show', $rendicion1));

    expect($gasto->fresh()->rendicion_id)->toBe($rendicion1->id);

    $respuesta = $this->post(route('panel.rendiciones.asociar_gasto', [$rendicion2, $gasto]));
    $respuesta->assertRedirect(route('panel.rendiciones.show', $rendicion2))
        ->assertSessionHasErrors('gasto');

    expect($gasto->fresh()->rendicion_id)->toBe($rendicion1->id);
});

it('publica el ítem de menú de rendiciones gateado por finanzas.rendicion.ver', function () {
    $itemMenu = SecMenu::query()->where('label', 'menu.financiero.items.rendiciones')->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'finanzas.rendicion.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.rendiciones.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
