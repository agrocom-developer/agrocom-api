<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;
use Database\Seeders\Catalogo\CatalogoSeeder;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Test end-to-end de HU-05 (tarea 13, etapa 4): el esqueleto vertical
 * completo hasta donde llega esta tarea — "piloto abre trabajo y sesión"
 * (TE-05) + "la cierra con hectáreas" (esta tarea) + "el jefe la ve en el
 * panel" (esta tarea), todo con los mismos endpoints que usa la app de
 * campo real (`POST /api/sync`) y la pantalla real del panel
 * (`GET /panel/trabajos`) — no un atajo directo a Eloquent.
 */

uses(RefreshDatabase::class);

it('abre, cierra y el jefe ve el trabajo cerrado en el panel', function () {
    $this->seed(CatalogoSeeder::class);
    $this->seed(DemoSeeder::class);

    // 1. El piloto abre trabajo y sesión vía POST /api/sync (TE-05).
    $operario = SecUser::factory()->create();
    $this->actingAs($operario, 'sanctum');

    $piloto = PerPersona::query()->create(['nombre' => 'Piloto e2e', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');
    $orden = OrdenAplicacion::query()
        ->where('lote_id', $loteId)
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();

    $this->postJson('/api/sync', ['registros' => [
        [
            'tipo' => 'trabajo',
            'uuid_cliente' => 'uuid-e2e-trabajo',
            'orden_id' => $orden->id,
            'lote_id' => $orden->lote_id,
            'nro_aplicacion' => $orden->nro_aplicacion,
            'hectareas_declaradas' => '18.40',
            'inicio' => '2026-09-01T08:00:00-04:00',
        ],
        [
            'tipo' => 'sesion',
            'uuid_cliente' => 'uuid-e2e-sesion',
            'trabajo_uuid_cliente' => 'uuid-e2e-trabajo',
            'secuencia' => 1,
            'piloto_id' => $piloto->id,
            'hectareas_declaradas' => '18.40',
            'inicio' => '2026-09-01T08:05:00-04:00',
        ],
    ]])->assertOk()->assertJsonPath('resultados.0.estado', 'aplicado')->assertJsonPath('resultados.1.estado', 'aplicado');

    // 2. El piloto la cierra con hectáreas (esta tarea) — sesión primero,
    // trabajo después, mismo lote.
    $cierre = $this->postJson('/api/sync', ['registros' => [
        [
            'tipo' => 'cierre_sesion',
            'uuid_cliente' => 'uuid-e2e-cierre-sesion',
            'sesion_uuid_cliente' => 'uuid-e2e-sesion',
            'fin' => '2026-09-01T09:30:00-04:00',
            'motivo_cierre' => 'completado',
        ],
        [
            'tipo' => 'cierre_trabajo',
            'uuid_cliente' => 'uuid-e2e-cierre-trabajo',
            'trabajo_uuid_cliente' => 'uuid-e2e-trabajo',
            'fin' => '2026-09-01T09:30:00-04:00',
        ],
    ]])->assertOk();

    expect($cierre->json('resultados.0.estado'))->toBe('aplicado')
        ->and($cierre->json('resultados.1.estado'))->toBe('aplicado');

    // Reintento del mismo cierre (p. ej. la app de campo reenvía la cola
    // offline sin haber visto la respuesta): sigue siendo idempotente.
    $reintento = $this->postJson('/api/sync', ['registros' => [
        [
            'tipo' => 'cierre_sesion',
            'uuid_cliente' => 'uuid-e2e-cierre-sesion',
            'sesion_uuid_cliente' => 'uuid-e2e-sesion',
            'fin' => '2026-09-01T09:30:00-04:00',
            'motivo_cierre' => 'completado',
        ],
    ]])->assertOk();
    expect($reintento->json('resultados.0.estado'))->toBe('duplicado');

    // 3. El jefe de campo lo ve cerrado en el panel (GET /panel/trabajos).
    $jefe = SecUser::factory()->create(['username' => 'jefe.e2e', 'password' => 'Secreta123']);
    $idRolJefe = (int) SecRole::query()->where('name', 'jefe_campo')->value('id');
    $pivote = new SecUserRole(['id_user' => $jefe->id, 'id_role' => $idRolJefe]);
    $pivote->created_by = $jefe->id;
    $pivote->updated_by = $jefe->id;
    $pivote->save();

    $this->actingAs(SecUsuarioInterno::query()->findOrFail($jefe->id), 'interno')
        ->withSession(['sec_rol_activo_id' => $idRolJefe]);

    $this->get('/panel/trabajos')
        ->assertOk()
        ->assertSee(__('operaciones.trabajos.estado.cerrado'))
        ->assertSee(__('operaciones.trabajos.motivo_cierre.completado'));
});
