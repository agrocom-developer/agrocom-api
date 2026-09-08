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
use Illuminate\Support\Carbon;

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
    return Cliente::query()->create(['razon_social' => 'Agropecuaria del Valle S.R.L.', 'nit' => '999888777']);
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

it('la transición borrador a vigente sin ninguna ventana cargada es rechazada por la guarda', function () {
    [$encargado, $idRol] = usuarioConRolParaContratos('encargado', 'encargado_operaciones');
    entrarAlPanelParaContratos($encargado, $idRol);
    $cliente = clienteParaContratos();

    // Contrato armado directo (fuera del flujo HTTP) sin ventanas: el alta
    // por panel siempre exige al menos una (`ventanas.min:1`), así que esta
    // guarda solo se ejercita con un contrato que llegó a `borrador` por
    // otro camino.
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
        ->assertSessionHasErrors('estado');

    expect($contrato->fresh()->estado)->toBe(EstadoContrato::Borrador);
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
    $otroCliente = Cliente::query()->create(['razon_social' => 'Agrícola San Marcos S.R.L.', 'nit' => '111222333']);
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
