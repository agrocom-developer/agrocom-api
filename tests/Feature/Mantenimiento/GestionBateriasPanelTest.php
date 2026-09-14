<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Recarga;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
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
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/*
 * HU-39 (tarea 51): catálogo de baterías con sus ciclos acumulados y estado,
 * para retirarlas antes de que fallen en vuelo. Segundo ABM del módulo
 * `Mantenimiento` (ADR 0011, extensión 3/9/2026). Permisos evaluados contra
 * el ROL ACTIVO de la sesión, nunca la unión de los roles del usuario
 * (invariante 10 de CLAUDE.md). Mismo patrón de asserts que
 * tests/Feature/Mantenimiento/GestionVehiculosPanelTest.php (tarea 50).
 *
 * `DemoSeeder` solo lo necesitan los dos tests de alerta por temperatura:
 * la orden vigente sobre el lote 'L-01' es la que le da un `sesion_id`
 * válido a la recarga de prueba (`ope_recargas` exige la FK real) — mismo
 * dato demo que usa `tests/Feature/Api/RecargaSincronizacionTest.php`.
 *
 * HU-83 (tarea 98) suma `ciclos_inicial` (punto de partida, inmutable tras
 * el alta) y el estado `mantenimiento` — ver los tests agregados más abajo.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaBaterias(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

/** Entra al panel con un rol activo fijado, como haría el login. */
function entrarAlPanelParaBaterias(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

/** Payload mínimo válido de alta/edición. */
function payloadBateria(array $overrides = []): array
{
    return array_merge([
        'identificador' => 'BAT-001',
        'ciclos_inicial' => '0',
        'ciclos_acumulados' => '0',
        'base_id' => '',
        'estado' => 'activa',
    ], $overrides);
}

/**
 * Orden vigente del dato demo (mismo lote 'L-01' que
 * `RecargaSincronizacionTest::ordenParaRecarga()`) — punto de partida para
 * construir la cadena `trabajo → sesión → recarga` que exige la FK real de
 * `ope_recargas.sesion_id`.
 */
function ordenVigenteParaBateria(): OrdenAplicacion
{
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');

    return OrdenAplicacion::query()
        ->where('lote_id', $loteId)
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();
}

/**
 * Escribe una recarga con `alerta_temperatura = true` para el
 * `$identificadorBateria` dado, directo por Eloquent (no por el motor de
 * sync — eso ya lo cubre `RecargaSincronizacionTest`; acá solo hace falta
 * la fila para probar la correlación de `LecturaAlertasTemperaturaBateria`).
 */
function crearRecargaConAlertaTemperatura(string $identificadorBateria): void
{
    $orden = ordenVigenteParaBateria();
    $piloto = PerPersona::query()->create(['nombre' => 'Piloto Batería', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $trabajo = Trabajo::query()->create([
        'uuid_cliente' => (string) Str::uuid(),
        'orden_id' => $orden->id,
        'lote_id' => $orden->lote_id,
        'nro_aplicacion' => $orden->nro_aplicacion,
        'inicio' => now(),
    ]);

    $sesion = Sesion::query()->create([
        'uuid_cliente' => (string) Str::uuid(),
        'trabajo_id' => $trabajo->id,
        'secuencia' => 1,
        'piloto_id' => $piloto->id,
        'inicio' => now(),
    ]);

    Recarga::query()->create([
        'uuid_cliente' => (string) Str::uuid(),
        'sesion_id' => $sesion->id,
        'secuencia' => 1,
        'litros_caldo' => '30.00',
        'bateria_saliente_id' => $identificadorBateria,
        'temperatura_bateria_c' => '55.00',
        'alerta_temperatura' => true,
        'hora' => now(),
    ]);
}

it('da de alta una batería con identificador, ciclos, base y estado válidos', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $base = PerBase::query()->create(['nombre' => 'Base Norte']);

    $this->post(route('panel.baterias.store'), payloadBateria(['base_id' => (string) $base->id, 'ciclos_acumulados' => '120', 'estado' => 'retirada']))
        ->assertRedirect(route('panel.baterias.index'));

    $bateria = Bateria::query()->where('identificador', 'BAT-001')->sole();

    expect($bateria->base_id)->toBe($base->id)
        ->and($bateria->ciclos_acumulados)->toBe(120)
        ->and($bateria->estado)->toBe('retirada');
});

it('da de alta una batería sin base asignada', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $this->post(route('panel.baterias.store'), payloadBateria())
        ->assertRedirect(route('panel.baterias.index'));

    $bateria = Bateria::query()->where('identificador', 'BAT-001')->sole();
    expect($bateria->base_id)->toBeNull()
        ->and($bateria->ciclos_inicial)->toBe(0)
        ->and($bateria->ciclos_acumulados)->toBe(0)
        ->and($bateria->estado)->toBe('activa');
});

it('da de alta una batería con ciclo inicial y acumulado distintos, guardando ambos valores', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $this->post(route('panel.baterias.store'), payloadBateria(['ciclos_inicial' => '40', 'ciclos_acumulados' => '120']))
        ->assertRedirect(route('panel.baterias.index'));

    $bateria = Bateria::query()->where('identificador', 'BAT-001')->sole();
    expect($bateria->ciclos_inicial)->toBe(40)
        ->and($bateria->ciclos_acumulados)->toBe(120);
});

it('rechaza ciclos_inicial negativo sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $this->post(route('panel.baterias.store'), payloadBateria(['ciclos_inicial' => '-1']))
        ->assertSessionHasErrors('ciclos_inicial');

    expect(Bateria::query()->where('identificador', 'BAT-001')->exists())->toBeFalse();
});

it('rechaza un estado fuera del enum sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $this->post(route('panel.baterias.store'), payloadBateria(['estado' => 'volando']))
        ->assertSessionHasErrors('estado');

    expect(Bateria::query()->where('identificador', 'BAT-001')->exists())->toBeFalse();
});

it('rechaza ciclos_acumulados negativos sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $this->post(route('panel.baterias.store'), payloadBateria(['ciclos_acumulados' => '-1']))
        ->assertSessionHasErrors('ciclos_acumulados');

    expect(Bateria::query()->where('identificador', 'BAT-001')->exists())->toBeFalse();
});

it('rechaza una base_id inexistente sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $this->post(route('panel.baterias.store'), payloadBateria(['base_id' => '999999']))
        ->assertSessionHasErrors('base_id');

    expect(Bateria::query()->where('identificador', 'BAT-001')->exists())->toBeFalse();
});

it('el identificador duplicado entre baterías activas es un error de validación, no un QueryException', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    Bateria::query()->create(['identificador' => 'BAT-001', 'estado' => 'activa']);

    $this->post(route('panel.baterias.store'), payloadBateria(['estado' => 'retirada']))
        ->assertSessionHasErrors('identificador');

    expect(Bateria::query()->where('estado', 'retirada')->exists())->toBeFalse();
});

it('una batería dada de baja no bloquea el re-alta con el mismo identificador', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $existente = Bateria::query()->create(['identificador' => 'BAT-001', 'estado' => 'activa']);
    $existente->delete();

    $this->post(route('panel.baterias.store'), payloadBateria())
        ->assertRedirect(route('panel.baterias.index'));

    expect(Bateria::query()->where('identificador', 'BAT-001')->count())->toBe(1);
});

it('edita una batería existente, incluidos sus ciclos acumulados', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $baseVieja = PerBase::query()->create(['nombre' => 'Base Norte']);
    $baseNueva = PerBase::query()->create(['nombre' => 'Base Sur']);

    $bateria = Bateria::query()->create(['identificador' => 'BAT-001', 'ciclos_acumulados' => 10, 'base_id' => $baseVieja->id, 'estado' => 'activa']);

    $this->put(
        route('panel.baterias.update', $bateria),
        payloadBateria(['identificador' => 'BAT-001-B', 'ciclos_acumulados' => '250', 'base_id' => (string) $baseNueva->id, 'estado' => 'retirada']),
    )->assertRedirect(route('panel.baterias.index'));

    $bateria->refresh();
    expect($bateria->identificador)->toBe('BAT-001-B')
        ->and($bateria->ciclos_acumulados)->toBe(250)
        ->and($bateria->base_id)->toBe($baseNueva->id)
        ->and($bateria->estado)->toBe('retirada');
});

it('ofrece ciclos_inicial editable en el alta, de solo lectura en la edición', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $this->get(route('panel.baterias.create'))
        ->assertOk()
        ->assertSee('name="ciclos_inicial"', false);

    $bateria = Bateria::query()->create(['identificador' => 'BAT-001', 'ciclos_inicial' => 40, 'ciclos_acumulados' => 40, 'estado' => 'activa']);

    $this->get(route('panel.baterias.edit', $bateria))
        ->assertOk()
        ->assertSee('40')
        ->assertDontSee('name="ciclos_inicial"', false);
});

it('actualizar ciclos_acumulados nunca pisa ciclos_inicial, aunque el payload lo incluya', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $bateria = Bateria::query()->create(['identificador' => 'BAT-001', 'ciclos_inicial' => 40, 'ciclos_acumulados' => 40, 'estado' => 'activa']);

    // `ciclos_inicial` viaja igual en el payload (por si el formulario lo
    // reenvía) pero `ActualizarBateriaRequest` no lo valida — no debe llegar
    // a `ActualizarBateria::ejecutar()` ni pisar el valor original.
    $this->put(
        route('panel.baterias.update', $bateria),
        payloadBateria(['ciclos_inicial' => '999', 'ciclos_acumulados' => '85']),
    )->assertRedirect(route('panel.baterias.index'));

    $bateria->refresh();
    expect($bateria->ciclos_inicial)->toBe(40)
        ->and($bateria->ciclos_acumulados)->toBe(85);
});

it('rechaza bajar ciclos_acumulados desde el panel sin motivo_correccion, sin persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $bateria = Bateria::query()->create(['identificador' => 'BAT-001', 'ciclos_acumulados' => 100, 'estado' => 'activa']);

    $this->put(
        route('panel.baterias.update', $bateria),
        payloadBateria(['ciclos_acumulados' => '80']),
    )->assertSessionHasErrors('motivo_correccion');

    expect($bateria->refresh()->ciclos_acumulados)->toBe(100);
});

it('acepta bajar ciclos_acumulados desde el panel con motivo_correccion', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $bateria = Bateria::query()->create(['identificador' => 'BAT-001', 'ciclos_acumulados' => 100, 'estado' => 'activa']);

    $this->put(
        route('panel.baterias.update', $bateria),
        payloadBateria(['ciclos_acumulados' => '80', 'motivo_correccion' => 'Corrección de carga inicial errónea']),
    )->assertRedirect(route('panel.baterias.index'));

    expect($bateria->refresh()->ciclos_acumulados)->toBe(80);
});

it('acepta el estado mantenimiento tanto al alta como a la edición', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $this->post(route('panel.baterias.store'), payloadBateria(['estado' => 'mantenimiento']))
        ->assertRedirect(route('panel.baterias.index'));

    $bateria = Bateria::query()->where('identificador', 'BAT-001')->sole();
    expect($bateria->estado)->toBe('mantenimiento');

    $this->put(
        route('panel.baterias.update', $bateria),
        payloadBateria(['estado' => 'activa']),
    )->assertRedirect(route('panel.baterias.index'));

    expect($bateria->refresh()->estado)->toBe('activa');

    $this->put(
        route('panel.baterias.update', $bateria),
        payloadBateria(['estado' => 'mantenimiento']),
    )->assertRedirect(route('panel.baterias.index'));

    expect($bateria->refresh()->estado)->toBe('mantenimiento');
});

it('filtra el listado por base y por estado', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $baseNorte = PerBase::query()->create(['nombre' => 'Base Norte']);
    $baseSur = PerBase::query()->create(['nombre' => 'Base Sur']);

    Bateria::query()->create(['identificador' => 'BAT-NORTE', 'base_id' => $baseNorte->id, 'estado' => 'activa']);
    Bateria::query()->create(['identificador' => 'BAT-SUR', 'base_id' => $baseSur->id, 'estado' => 'retirada']);

    $this->get(route('panel.baterias.index', ['base_id' => $baseNorte->id]))
        ->assertOk()
        ->assertSee('BAT-NORTE')
        ->assertDontSee('BAT-SUR');

    $this->get(route('panel.baterias.index', ['estado' => 'retirada']))
        ->assertOk()
        ->assertSee('BAT-SUR')
        ->assertDontSee('BAT-NORTE');
});

it('lista y filtra baterías en estado mantenimiento sin romper el badge', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    Bateria::query()->create(['identificador' => 'BAT-MANT', 'estado' => 'mantenimiento']);
    Bateria::query()->create(['identificador' => 'BAT-ACTIVA', 'estado' => 'activa']);

    $this->get(route('panel.baterias.index', ['estado' => 'mantenimiento']))
        ->assertOk()
        ->assertSee('BAT-MANT')
        ->assertDontSee('BAT-ACTIVA');
});

it('activa la alerta cuando los ciclos acumulados alcanzan el umbral, no antes', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    Bateria::query()->create(['identificador' => 'BAT-BAJO', 'ciclos_acumulados' => Bateria::UMBRAL_CICLOS_ALERTA - 1, 'estado' => 'activa']);
    Bateria::query()->create(['identificador' => 'BAT-ALTO', 'ciclos_acumulados' => Bateria::UMBRAL_CICLOS_ALERTA, 'estado' => 'activa']);

    $this->get(route('panel.baterias.index', ['q' => 'BAT-BAJO']))
        ->assertOk()
        ->assertDontSee('battery_alert');

    $this->get(route('panel.baterias.index', ['q' => 'BAT-ALTO']))
        ->assertOk()
        ->assertSee('battery_alert');
});

it('activa la alerta cuando una recarga marcó temperatura por encima del umbral para el identificador de la batería, sin alerta si no hay ninguna', function () {
    $this->seed(DemoSeeder::class);

    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    Bateria::query()->create(['identificador' => 'BAT-TEMP', 'ciclos_acumulados' => 5, 'estado' => 'activa']);

    $this->get(route('panel.baterias.index', ['q' => 'BAT-TEMP']))
        ->assertOk()
        ->assertDontSee('battery_alert');

    crearRecargaConAlertaTemperatura('BAT-TEMP');

    $this->get(route('panel.baterias.index', ['q' => 'BAT-TEMP']))
        ->assertOk()
        ->assertSee('battery_alert');
});

it('registra en bitácora el alta, la edición y la baja de una batería', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $this->post(route('panel.baterias.store'), payloadBateria());

    $bateria = Bateria::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'man_baterias')
        ->where('registro_id', $bateria->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['identificador'])->toBe('BAT-001');

    $this->put(
        route('panel.baterias.update', $bateria),
        payloadBateria(['identificador' => 'BAT-001-B', 'ciclos_acumulados' => '50']),
    )->assertRedirect(route('panel.baterias.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'man_baterias')
        ->where('registro_id', $bateria->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['identificador'])->toBe('BAT-001-B')
        ->and($filaActualizado->despues['ciclos_acumulados'])->toBe(50);

    $this->delete(route('panel.baterias.destroy', $bateria))
        ->assertRedirect(route('panel.baterias.index'));

    Bitacora::query()
        ->where('tabla', 'man_baterias')
        ->where('registro_id', $bateria->id)
        ->where('accion', AccionBitacora::Eliminado)
        ->sole();
});

it('da de baja una batería por soft delete: no aparece en el índice y un segundo intento da 404', function () {
    [$encargado, $idRol] = usuarioConRolParaBaterias('encargado', 'encargado_operaciones');
    entrarAlPanelParaBaterias($encargado, $idRol);

    $this->post(route('panel.baterias.store'), payloadBateria());
    $bateria = Bateria::query()->sole();

    $this->delete(route('panel.baterias.destroy', $bateria))
        ->assertRedirect(route('panel.baterias.index'));

    $borrada = Bateria::withTrashed()->findOrFail($bateria->id);
    expect($borrada->trashed())->toBeTrue();

    $this->get(route('panel.baterias.index'))
        ->assertOk()
        ->assertDontSee('BAT-001');

    // El route model binding no resuelve filas borradas lógicamente: 404, no
    // un segundo borrado silencioso.
    $this->delete(route('panel.baterias.destroy', $bateria))->assertNotFound();
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaBaterias('piloto.curioso', 'piloto');
    entrarAlPanelParaBaterias($piloto, $idRol);

    $bateria = Bateria::query()->create(['identificador' => 'BAT-EXISTENTE', 'estado' => 'activa']);

    $this->get(route('panel.baterias.index'))->assertForbidden();
    $this->get(route('panel.baterias.create'))->assertForbidden();
    $this->post(route('panel.baterias.store'), payloadBateria())->assertForbidden();
    $this->get(route('panel.baterias.edit', $bateria))->assertForbidden();
    $this->put(route('panel.baterias.update', $bateria), payloadBateria())->assertForbidden();
    $this->delete(route('panel.baterias.destroy', $bateria))->assertForbidden();

    expect(Bateria::query()->count())->toBe(1)
        ->and($bateria->fresh()?->trashed())->toBeFalse();
});

it('no deja actuar a quien tiene el permiso en otro rol pero no en el activo', function () {
    // Multirol: encargado (con el permiso) + piloto (sin él). Opera bajo
    // piloto, así que NO puede dar de alta — los permisos efectivos son los
    // del rol activo, jamás la unión.
    [$multirol, $idEncargado] = usuarioConRolParaBaterias('jefe.multirol', 'encargado_operaciones');
    $idPiloto = (int) SecRole::query()->where('name', 'piloto')->value('id');
    $pivote = new SecUserRole(['id_user' => $multirol->id, 'id_role' => $idPiloto]);
    $pivote->created_by = $multirol->id;
    $pivote->updated_by = $multirol->id;
    $pivote->save();

    entrarAlPanelParaBaterias($multirol, $idPiloto);
    $this->post(route('panel.baterias.store'), payloadBateria())->assertForbidden();
    expect(Bateria::query()->count())->toBe(0);

    // Con el rol activo correcto, la misma cuenta sí puede.
    entrarAlPanelParaBaterias($multirol, $idEncargado);
    $this->post(route('panel.baterias.store'), payloadBateria())->assertRedirect();
    expect(Bateria::query()->count())->toBe(1);
});

it('publica el ítem de menú de baterías gateado por mantenimiento.bateria.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.recursos.items.baterias')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'mantenimiento.bateria.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.baterias.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
