<?php

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * HU-23 (tarea 34): administración de contratos con sus ventanas de
 * aplicación — segundo ABM del panel, mismo molde que
 * tests/Feature/Comercial/GestionClientesPanelTest.php (tarea 33) con una
 * máquina de estados encima. Permisos evaluados contra el ROL ACTIVO de la
 * sesión (invariante 10 de CLAUDE.md).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaContratos(string $username, string $rol): array
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
function entrarAlPanelParaContratos(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function clienteParaContratos(): Cliente
{
    return Cliente::query()->create(['razon_social' => 'Agropecuaria del Valle S.R.L.', 'nit' => '999888777', 'tipo_persona' => 'juridica']);
}

/** Campaña `abierta` del cliente (ADR 0015 punto 1): el contrato la exige. */
function campaniaParaContratos(int $clienteId): Campania
{
    return Campania::query()->create([
        'cliente_id' => $clienteId,
        'codigo' => '2025-2026',
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'abierta',
    ]);
}

/** Payload mínimo válido de alta/edición: dos ventanas que no se solapan. */
function payloadContrato(int $clienteId, int $campaniaId, array $overrides = []): array
{
    return array_merge([
        'cliente_id' => $clienteId,
        'campania_id' => $campaniaId,
        'hectareas_contratadas' => '100.00',
        'aplicaciones_previstas' => 3,
        'precio_ha' => '50.00',
        'fecha_inicio' => Carbon::tomorrow()->toDateString(),
        'ventanas' => [
            ['hora_inicio' => '06:00', 'hora_fin' => '10:00'],
            ['hora_inicio' => '16:00', 'hora_fin' => '20:00'],
        ],
    ], $overrides);
}

it('da de alta un contrato con dos ventanas que no se solapan, con monto_total calculado exacto', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campania->id))
        ->assertRedirect(route('panel.contratos.index'));

    $contrato = Contrato::query()->where('cliente_id', $cliente->id)->sole();

    expect($contrato->monto_total)->toBe('15000.00')
        ->and($contrato->estado)->toBe(EstadoContrato::Borrador)
        ->and($contrato->ventanas()->count())->toBe(2);
});

it('una ventana que se solapa con otra del mismo contrato es un error de validación, no persiste ninguna', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campania->id, [
        'ventanas' => [
            ['hora_inicio' => '06:00', 'hora_fin' => '10:00'],
            ['hora_inicio' => '08:00', 'hora_fin' => '12:00'],
        ],
    ]))->assertSessionHasErrors('ventanas');

    expect(Contrato::query()->where('cliente_id', $cliente->id)->exists())->toBeFalse();
});

it('la transición borrador a vigente sin ninguna ventana cargada pasa sin error (HU-47: día completo)', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();

    // Contrato armado directo (fuera del flujo HTTP) sin ventanas: el alta
    // por panel ya no exige ninguna desde la tarea 70 — cero ventanas es
    // "día completo", un contrato válido, no uno incompleto.
    $contrato = new Contrato([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '100.00',
        'aplicaciones_previstas' => 3,
        'precio_ha' => '50.00',
        'monto_total' => '15000.00',
        'fecha_inicio' => Carbon::tomorrow()->toDateString(),
        'estado' => EstadoContrato::Borrador,
    ]);
    $contrato->save();

    $this->post(route('panel.contratos.cambiar-estado', $contrato), ['estado' => 'vigente'])
        ->assertRedirect(route('panel.contratos.index'))
        ->assertSessionDoesntHaveErrors();

    expect($contrato->fresh()->estado)->toBe(EstadoContrato::Vigente);
});

it('da de alta un contrato sin tocar el interruptor de ventanas: día completo, ningún campo de hora es obligatorio', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    // Sin la clave `ventanas` en absoluto: el interruptor "Día completo"
    // arranca encendido y el formulario ni la manda (etapa 2, tarea 70).
    $payload = Arr::except(payloadContrato($cliente->id, $campania->id), ['ventanas']);

    $this->post(route('panel.contratos.store'), $payload)
        ->assertRedirect(route('panel.contratos.index'));

    $contrato = Contrato::query()->where('cliente_id', $cliente->id)->sole();

    expect($contrato->ventanas()->count())->toBe(0);
});

it('una fila de ventana con una sola hora cargada es un error de validación, no persiste', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campania->id, [
        'ventanas' => [
            ['hora_inicio' => '06:00', 'hora_fin' => ''],
        ],
    ]))->assertSessionHasErrors('ventanas.0.hora_fin');

    expect(Contrato::query()->where('cliente_id', $cliente->id)->exists())->toBeFalse();
});

it('altura_vuelo_m cero o negativa es un error de validación, no persiste', function (string $valor) {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campania->id, [
        'altura_vuelo_m' => $valor,
    ]))->assertSessionHasErrors('altura_vuelo_m');

    expect(Contrato::query()->where('cliente_id', $cliente->id)->exists())->toBeFalse();
})->with(['0', '-1']);

it('guarda la altura de vuelo del contrato cuando se informa', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campania->id, [
        'altura_vuelo_m' => '3.50',
    ]))->assertRedirect(route('panel.contratos.index'));

    $contrato = Contrato::query()->where('cliente_id', $cliente->id)->sole();

    expect($contrato->altura_vuelo_m)->toBe('3.50');
});

it('da de alta un contrato con las tres coberturas logísticas activas y observaciones (HU-74, tarea 90)', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campania->id, [
        'brinda_alimentacion' => '1',
        'brinda_hospedaje' => '1',
        'brinda_combustible' => '1',
        'observaciones_logistica' => 'Hospedaje en la posta del campo; combustible lo provee Agrocom.',
    ]))->assertRedirect(route('panel.contratos.index'));

    $contrato = Contrato::query()->where('cliente_id', $cliente->id)->sole();

    expect($contrato->brinda_alimentacion)->toBeTrue()
        ->and($contrato->brinda_hospedaje)->toBeTrue()
        ->and($contrato->brinda_combustible)->toBeTrue()
        ->and($contrato->observaciones_logistica)->toBe('Hospedaje en la posta del campo; combustible lo provee Agrocom.');
});

it('un contrato sin logística informada se crea igual, con los tres booleanos en false (HU-74, tarea 90)', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campania->id))
        ->assertRedirect(route('panel.contratos.index'));

    $contrato = Contrato::query()->where('cliente_id', $cliente->id)->sole();

    expect($contrato->brinda_alimentacion)->toBeFalse()
        ->and($contrato->brinda_hospedaje)->toBeFalse()
        ->and($contrato->brinda_combustible)->toBeFalse()
        ->and($contrato->observaciones_logistica)->toBeNull();
});

it('editar un contrato sin marcar ninguna cobertura logística la deja en false, no en su valor anterior (HU-74, tarea 90)', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campania->id, [
        'brinda_alimentacion' => '1',
        'brinda_hospedaje' => '1',
        'brinda_combustible' => '1',
        'observaciones_logistica' => 'Cobertura completa inicial.',
    ]));
    $contrato = Contrato::query()->where('cliente_id', $cliente->id)->sole();

    // Ningún checkbox va en este envío: un checkbox sin marcar no llega en el
    // POST, así que el controlador tiene que leerlo con `$request->boolean()`
    // y guardar `false` explícito, no dejar el valor anterior sin tocar.
    $this->put(route('panel.contratos.update', $contrato), payloadContrato($cliente->id, $campania->id))
        ->assertRedirect(route('panel.contratos.index'));

    $contratoActualizado = $contrato->fresh();

    expect($contratoActualizado->brinda_alimentacion)->toBeFalse()
        ->and($contratoActualizado->brinda_hospedaje)->toBeFalse()
        ->and($contratoActualizado->brinda_combustible)->toBeFalse();
});

it('el formulario de contrato muestra la sección de logística (HU-74, tarea 90)', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);

    $this->get(route('panel.contratos.create'))
        ->assertOk()
        ->assertSee(__('comercial.contratos.seccion_logistica'))
        ->assertSee(__('comercial.contratos.campo_brinda_alimentacion'))
        ->assertSee(__('comercial.contratos.campo_brinda_hospedaje'))
        ->assertSee(__('comercial.contratos.campo_brinda_combustible'))
        ->assertSee(__('comercial.contratos.campo_observaciones_logistica'));
});

it('la ficha de edición de un contrato muestra las observaciones de logística guardadas (HU-74, tarea 90)', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campania->id, [
        'observaciones_logistica' => 'Alojamiento cubierto en la base de operaciones.',
    ]));
    $contrato = Contrato::query()->where('cliente_id', $cliente->id)->sole();

    $this->get(route('panel.contratos.edit', $contrato))
        ->assertOk()
        ->assertSee('Alojamiento cubierto en la base de operaciones.');
});

it('el formulario de alta muestra el interruptor "Día completo"', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);

    $this->get(route('panel.contratos.create'))
        ->assertOk()
        ->assertSee(__('comercial.contratos.ventana_dia_completo'));
});

it('el listado muestra "Día completo" para un contrato sin ventanas cargadas', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    $payload = Arr::except(payloadContrato($cliente->id, $campania->id), ['ventanas']);
    $this->post(route('panel.contratos.store'), $payload);

    $this->get(route('panel.contratos.index'))
        ->assertOk()
        ->assertSee(__('comercial.contratos.ventana_dia_completo'));
});

it('la ficha de edición de un contrato con ventanas cargadas las muestra, con el interruptor apagado', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campania->id));
    $contrato = Contrato::query()->where('cliente_id', $cliente->id)->sole();

    $this->get(route('panel.contratos.edit', $contrato))
        ->assertOk()
        ->assertSee('06:00')
        ->assertSee('16:00');
});

it('una transición inválida es rechazada por la máquina de estados y no llega a persistir', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();

    $contrato = new Contrato([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '100.00',
        'aplicaciones_previstas' => 3,
        'precio_ha' => '50.00',
        'monto_total' => '15000.00',
        'fecha_inicio' => Carbon::yesterday()->toDateString(),
        'estado' => EstadoContrato::Finalizado,
    ]);
    $contrato->save();

    $this->post(route('panel.contratos.cambiar-estado', $contrato), ['estado' => 'vigente'])
        ->assertRedirect(route('panel.contratos.index'))
        ->assertSessionHasErrors('estado');

    expect($contrato->fresh()->estado)->toBe(EstadoContrato::Finalizado);
});

it('un contrato vigente pasa a pausado y de vuelta a vigente (HU-71, tarea 87)', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();

    $contrato = new Contrato([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '100.00',
        'aplicaciones_previstas' => 3,
        'precio_ha' => '50.00',
        'monto_total' => '15000.00',
        'fecha_inicio' => Carbon::yesterday()->toDateString(),
        'estado' => EstadoContrato::Vigente,
    ]);
    $contrato->save();

    $this->post(route('panel.contratos.cambiar-estado', $contrato), ['estado' => 'pausado'])
        ->assertRedirect(route('panel.contratos.index'))
        ->assertSessionDoesntHaveErrors();

    expect($contrato->fresh()->estado)->toBe(EstadoContrato::Pausado);

    // La vuelta a vigente NO pasa por la guarda de `activar()` (fecha_inicio
    // no en el pasado): este contrato ya tiene `fecha_inicio` ayer, y si
    // `cambiarA()` no distinguiera el origen `pausado` de `borrador`,
    // reanudar fallaría siempre para un contrato que ya estuvo vigente.
    $this->post(route('panel.contratos.cambiar-estado', $contrato), ['estado' => 'vigente'])
        ->assertRedirect(route('panel.contratos.index'))
        ->assertSessionDoesntHaveErrors();

    expect($contrato->fresh()->estado)->toBe(EstadoContrato::Vigente);
});

it('borrador a pausado se rechaza: no está en la tabla de transiciones (HU-71, tarea 87)', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();

    $contrato = new Contrato([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '100.00',
        'aplicaciones_previstas' => 3,
        'precio_ha' => '50.00',
        'monto_total' => '15000.00',
        'fecha_inicio' => Carbon::tomorrow()->toDateString(),
        'estado' => EstadoContrato::Borrador,
    ]);
    $contrato->save();

    $this->post(route('panel.contratos.cambiar-estado', $contrato), ['estado' => 'pausado'])
        ->assertRedirect(route('panel.contratos.index'))
        ->assertSessionHasErrors('estado');

    expect($contrato->fresh()->estado)->toBe(EstadoContrato::Borrador);
});

it('pausado a cancelado se rechaza: el CA de HU-71 solo pide ida y vuelta con vigente', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();

    $contrato = new Contrato([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '100.00',
        'aplicaciones_previstas' => 3,
        'precio_ha' => '50.00',
        'monto_total' => '15000.00',
        'fecha_inicio' => Carbon::yesterday()->toDateString(),
        'estado' => EstadoContrato::Pausado,
    ]);
    $contrato->save();

    $this->post(route('panel.contratos.cambiar-estado', $contrato), ['estado' => 'cancelado'])
        ->assertRedirect(route('panel.contratos.index'))
        ->assertSessionHasErrors('estado');

    expect($contrato->fresh()->estado)->toBe(EstadoContrato::Pausado);
});

it('el CHECK de estado admite pausado sin alterar el valor de un contrato vigente existente (solo pgsql)', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('CHECK solo existe en pgsql; SQLite no soporta ADD CONSTRAINT (ver docblock de la migración 2026_09_13_100001).');
    }

    $cliente = clienteParaContratos();
    $contrato = new Contrato([
        'cliente_id' => $cliente->id,
        'hectareas_contratadas' => '100.00',
        'aplicaciones_previstas' => 3,
        'precio_ha' => '50.00',
        'monto_total' => '15000.00',
        'fecha_inicio' => Carbon::yesterday()->toDateString(),
        'estado' => EstadoContrato::Vigente,
    ]);
    $contrato->save();

    // Regresión (tarea 87): RefreshDatabase ya corrió la migración nueva
    // antes de este test, así que se la vuelve a correr sobre un esquema que
    // ya la tiene aplicada — simula el caso real, una fila `vigente`
    // preexistente antes del ALTER. DROP+ADD CONSTRAINT del mismo CHECK es
    // idempotente y no toca ninguna columna de datos.
    (require database_path('migrations/2026_09_13_100001_add_pausado_a_com_contratos_estado_chk.php'))->up();

    expect($contrato->fresh()->estado)->toBe(EstadoContrato::Vigente);

    DB::table('com_contratos')->where('id', $contrato->id)->update(['estado' => 'pausado']);
    expect($contrato->fresh()->estado)->toBe(EstadoContrato::Pausado);
});

it('registra en bitácora el alta y el cambio de estado de un contrato', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campania->id));

    $contrato = Contrato::query()->sole();

    $filaCreado = Bitacora::query()
        ->where('tabla', 'com_contratos')
        ->where('registro_id', $contrato->id)
        ->where('accion', AccionBitacora::Creado)
        ->sole();

    expect($filaCreado->user_id)->toBe($encargado->id)
        ->and($filaCreado->despues['monto_total'])->toBe('15000.00');

    $this->post(route('panel.contratos.cambiar-estado', $contrato), ['estado' => 'vigente'])
        ->assertRedirect(route('panel.contratos.index'));

    $filaActualizado = Bitacora::query()
        ->where('tabla', 'com_contratos')
        ->where('registro_id', $contrato->id)
        ->where('accion', AccionBitacora::Actualizado)
        ->sole();

    expect($filaActualizado->despues['estado'])->toBe('vigente');
});

it('un rol sin el permiso recibe 403 en todas las acciones', function () {
    [$piloto, $idRol] = usuarioConRolParaContratos('piloto.curioso', 'piloto');
    entrarAlPanelParaContratos($piloto, $idRol);
    $cliente = clienteParaContratos();
    $campania = campaniaParaContratos($cliente->id);

    $contrato = new Contrato([
        'cliente_id' => $cliente->id,
        'campania_id' => $campania->id,
        'hectareas_contratadas' => '100.00',
        'aplicaciones_previstas' => 3,
        'precio_ha' => '50.00',
        'monto_total' => '15000.00',
        'fecha_inicio' => Carbon::tomorrow()->toDateString(),
        'estado' => EstadoContrato::Borrador,
    ]);
    $contrato->save();

    $this->get(route('panel.contratos.index'))->assertForbidden();
    $this->get(route('panel.contratos.create'))->assertForbidden();
    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campania->id))->assertForbidden();
    $this->get(route('panel.contratos.edit', $contrato))->assertForbidden();
    $this->put(route('panel.contratos.update', $contrato), payloadContrato($cliente->id, $campania->id))->assertForbidden();
    $this->post(route('panel.contratos.cambiar-estado', $contrato), ['estado' => 'vigente'])->assertForbidden();

    expect(Contrato::query()->count())->toBe(1)
        ->and($contrato->fresh()->estado)->toBe(EstadoContrato::Borrador);
});

it('rechaza crear un contrato con una campaña de otro cliente', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $otroCliente = Cliente::query()->create(['razon_social' => 'Agrícola San Marcos S.R.L.', 'nit' => '111222333', 'tipo_persona' => 'juridica']);
    $campaniaAjena = campaniaParaContratos($otroCliente->id);

    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campaniaAjena->id))
        ->assertRedirect(route('panel.contratos.create'))
        ->assertSessionHasErrors('campania_id');

    expect(Contrato::query()->where('cliente_id', $cliente->id)->exists())->toBeFalse();
});

it('rechaza crear un contrato contra una campaña cerrada', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();
    $campaniaCerrada = Campania::query()->create([
        'cliente_id' => $cliente->id,
        'codigo' => '2024-2025',
        'fecha_inicio' => '2024-07-01',
        'fecha_fin' => '2025-06-30',
        'estado' => 'cerrada',
    ]);

    $this->post(route('panel.contratos.store'), payloadContrato($cliente->id, $campaniaCerrada->id))
        ->assertRedirect(route('panel.contratos.create'))
        ->assertSessionHasErrors('campania_id');

    expect(Contrato::query()->where('cliente_id', $cliente->id)->exists())->toBeFalse();
});

it('publica el ítem de menú de contratos gateado por comercial.contrato.ver', function () {
    $itemMenu = SecMenu::query()
        ->where('label', 'menu.comercial.items.contratos')
        ->sole();

    $idPermiso = (int) SecPermission::query()
        ->where('code', 'comercial.contrato.ver')
        ->value('id');

    expect($itemMenu->ruta)->toBe('panel.contratos.index')
        ->and($itemMenu->permission_id)->toBe($idPermiso);
});
