<?php

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * HU-48 (tarea 71, etapa 3, ADR 0015 punto 4): pantalla de siembra en la
 * ficha del campo. Reusa el permiso `comercial.campo.editar` (no es un ABM
 * propio). Mismo patrón de asserts que
 * tests/Feature/Comercial/GestionCamposPanelTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CatalogoSeeder::class);
});

function usuarioConRolParaSiembraPanel(string $username, string $rol): array
{
    $usuario = SecUser::factory()->create(['username' => $username, 'password' => 'Secreta123']);
    $idRol = (int) SecRole::query()->where('name', $rol)->value('id');

    $pivote = new SecUserRole(['id_user' => $usuario->id, 'id_role' => $idRol]);
    $pivote->created_by = $usuario->id;
    $pivote->updated_by = $usuario->id;
    $pivote->save();

    return [$usuario, $idRol];
}

function entrarAlPanelParaSiembra(SecUser $usuario, int $idRolActivo): void
{
    test()->actingAs(SecUsuarioInterno::query()->findOrFail($usuario->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolActivo]);
}

function clienteParaSiembraPanel(string $razonSocial = 'Agropecuaria Panel Siembra S.R.L.'): Cliente
{
    return Cliente::query()->create(['razon_social' => $razonSocial]);
}

function campaniaParaSiembraPanel(int $clienteId, string $codigo = '2025-2026'): Campania
{
    return Campania::query()->create([
        'cliente_id' => $clienteId,
        'codigo' => $codigo,
        'fecha_inicio' => '2025-07-01',
        'fecha_fin' => '2026-06-30',
        'estado' => 'abierta',
    ]);
}

function campoConLotesParaSiembraPanel(int $clienteId): Campo
{
    $campo = Campo::query()->create(['cliente_id' => $clienteId, 'nombre' => 'Campo panel siembra']);
    $campo->lotes()->create(['codigo' => 'L-01', 'hectareas' => '20.00']);
    $campo->lotes()->create(['codigo' => 'L-02', 'hectareas' => '15.00']);

    return $campo->refresh()->load('lotes');
}

function cultivoIdParaSiembraPanel(string $nombre): int
{
    return (int) Cultivo::query()->where('nombre', $nombre)->value('id');
}

it('muestra el mensaje para crear una campaña cuando el cliente todavía no tiene ninguna', function () {
    [$encargado, $idRol] = usuarioConRolParaSiembraPanel('encargado', 'encargado_operaciones');
    entrarAlPanelParaSiembra($encargado, $idRol);

    $cliente = clienteParaSiembraPanel();
    $campo = campoConLotesParaSiembraPanel($cliente->id);

    $this->get(route('panel.campos.siembra', $campo))
        ->assertOk()
        ->assertSee(__('comercial.siembra.sin_campanias'));
});

it('guarda la siembra de los lotes de un campo para una campaña', function () {
    [$encargado, $idRol] = usuarioConRolParaSiembraPanel('encargado', 'encargado_operaciones');
    entrarAlPanelParaSiembra($encargado, $idRol);

    $cliente = clienteParaSiembraPanel();
    $campania = campaniaParaSiembraPanel($cliente->id);
    $campo = campoConLotesParaSiembraPanel($cliente->id);
    [$loteUno, $loteDos] = $campo->lotes->all();

    $this->post(route('panel.campos.siembra.guardar', $campo), [
        'campania_id' => $campania->id,
        'lotes' => [
            ['lote_id' => $loteUno->id, 'cultivo_id' => cultivoIdParaSiembraPanel('Soya'), 'hectareas_sembradas' => '18.00', 'fecha_siembra' => '2025-11-01', 'fecha_cosecha_estimada' => '2026-03-01'],
            ['lote_id' => $loteDos->id, 'cultivo_id' => '', 'hectareas_sembradas' => '', 'fecha_siembra' => '', 'fecha_cosecha_estimada' => ''],
        ],
    ])->assertRedirect(route('panel.campos.siembra', ['campo' => $campo, 'campania_id' => $campania->id]));

    $siembra = LoteCampania::query()->where('lote_id', $loteUno->id)->where('campania_id', $campania->id)->sole();
    expect((string) $siembra->hectareas_sembradas)->toBe('18.00');

    expect(LoteCampania::query()->where('lote_id', $loteDos->id)->where('campania_id', $campania->id)->exists())->toBeFalse();
});

it('rechaza hectáreas sembradas por encima de las del lote, con un mensaje traducido', function () {
    [$encargado, $idRol] = usuarioConRolParaSiembraPanel('encargado', 'encargado_operaciones');
    entrarAlPanelParaSiembra($encargado, $idRol);

    $cliente = clienteParaSiembraPanel();
    $campania = campaniaParaSiembraPanel($cliente->id);
    $campo = campoConLotesParaSiembraPanel($cliente->id);
    $lote = $campo->lotes->first();

    $this->from(route('panel.campos.siembra', $campo))
        ->post(route('panel.campos.siembra.guardar', $campo), [
            'campania_id' => $campania->id,
            'lotes' => [
                ['lote_id' => $lote->id, 'cultivo_id' => cultivoIdParaSiembraPanel('Soya'), 'hectareas_sembradas' => '999.00', 'fecha_siembra' => '', 'fecha_cosecha_estimada' => ''],
            ],
        ])
        ->assertRedirect(route('panel.campos.siembra', ['campo' => $campo, 'campania_id' => $campania->id]))
        ->assertSessionHasErrors('lotes');

    expect(LoteCampania::query()->where('lote_id', $lote->id)->exists())->toBeFalse();
});

it('un rol sin el permiso recibe 403', function () {
    [$piloto, $idRol] = usuarioConRolParaSiembraPanel('piloto.curioso', 'piloto');
    entrarAlPanelParaSiembra($piloto, $idRol);

    $cliente = clienteParaSiembraPanel();
    $campania = campaniaParaSiembraPanel($cliente->id);
    $campo = campoConLotesParaSiembraPanel($cliente->id);
    $lote = $campo->lotes->first();

    $this->get(route('panel.campos.siembra', $campo))->assertForbidden();

    // Payload válido (pasaría la validación): el 403 tiene que llegar por el
    // permiso, no ser un 302 de validación disfrazado.
    $this->post(route('panel.campos.siembra.guardar', $campo), [
        'campania_id' => $campania->id,
        'lotes' => [
            ['lote_id' => $lote->id, 'cultivo_id' => cultivoIdParaSiembraPanel('Soya'), 'hectareas_sembradas' => '5.00', 'fecha_siembra' => '', 'fecha_cosecha_estimada' => ''],
        ],
    ])->assertForbidden();

    expect(LoteCampania::query()->where('lote_id', $lote->id)->exists())->toBeFalse();
});
