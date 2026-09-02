<?php

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * POST /api/sync — prueba de reconciliación (tarea 27): un lote con un
 * registro de CADA tipo que `SincronizarLote::ORDEN_CAUSAL` acepta hoy,
 * todos en un único push. Ninguna de las HU que extendieron el motor de
 * sync (17/condiciones, 18/recepcion_caldo, 22/incidencia, 23/recarga) tiene
 * su propio test de convivencia con las demás — cada una se probó aislada
 * en su propio archivo. Este es ese test: si el merge de dos ramas que
 * tocaron el mismo `match`/enum hubiera perdido un tipo, acá saldría
 * "tipo de registro desconocido" en vez de "aplicado".
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(SecUser::factory()->create(), 'sanctum');
});

it('un lote con un registro de cada tipo aceptado por el sync aplica todos', function () {
    $loteId = Lote::query()->where('codigo', 'L-01')->value('id');
    $orden = OrdenAplicacion::query()
        ->where('lote_id', $loteId)
        ->where('estado', EstadoOrdenAplicacion::Vigente)
        ->firstOrFail();

    $piloto = PerPersona::query()->create(['nombre' => 'Piloto Reconciliación', 'rol' => RolOperativoPersona::Piloto, 'activo' => true]);

    $evidenciaFoto = Evidencia::query()->create([
        'uuid_cliente' => 'uuid-evidencia-foto-todos',
        'tipo' => TipoEvidencia::FotoIncidencia,
        'archivo_url' => 'evidencias/foto_incidencia/2026/09/uuid-evidencia-foto-todos.jpg',
        'hash' => hash('sha256', 'uuid-evidencia-foto-todos'),
        'fecha' => '2026-09-01T09:00:00-04:00',
    ])->uuid_cliente;

    $evidenciaImagenCampo = Evidencia::query()->create([
        'uuid_cliente' => 'uuid-evidencia-campo-todos',
        'tipo' => TipoEvidencia::ImagenCampo,
        'archivo_url' => 'evidencias/imagen_campo/2026/09/uuid-evidencia-campo-todos.jpg',
        'hash' => hash('sha256', 'uuid-evidencia-campo-todos'),
        'fecha' => '2026-09-01T09:00:00-04:00',
    ])->uuid_cliente;

    $respuesta = $this->postJson('/api/sync', [
        'registros' => [
            [
                'tipo' => 'trabajo',
                'uuid_cliente' => 'uuid-todos-trabajo',
                'orden_id' => $orden->id,
                'lote_id' => $orden->lote_id,
                'nro_aplicacion' => $orden->nro_aplicacion,
                'inicio' => '2026-09-01T10:00:00-04:00',
            ],
            [
                'tipo' => 'recepcion_caldo',
                'uuid_cliente' => 'uuid-todos-recepcion-caldo',
                'trabajo_uuid_cliente' => 'uuid-todos-trabajo',
                'litros' => '150.00',
                'entregado_por' => 'Ing. Agr. del cliente',
                'hora' => '2026-09-01T09:45:00-04:00',
            ],
            [
                'tipo' => 'sesion',
                'uuid_cliente' => 'uuid-todos-sesion',
                'trabajo_uuid_cliente' => 'uuid-todos-trabajo',
                'secuencia' => 1,
                'piloto_id' => $piloto->id,
                'inicio' => '2026-09-01T10:05:00-04:00',
            ],
            [
                'tipo' => 'condiciones',
                'uuid_cliente' => 'uuid-todos-condiciones',
                'sesion_uuid_cliente' => 'uuid-todos-sesion',
                'momento' => 'inicio_sesion',
                'viento_kmh' => '10.00',
                'temperatura_c' => '22.00',
                'humedad_pct' => '60.00',
            ],
            [
                'tipo' => 'incidencia',
                'uuid_cliente' => 'uuid-todos-incidencia',
                'sesion_uuid_cliente' => 'uuid-todos-sesion',
                'tipo_incidencia' => 'clima',
                'descripcion' => 'ráfaga inesperada',
                'hora' => '2026-09-01T10:20:00-04:00',
                'evidencia_foto_uuid_cliente' => $evidenciaFoto,
            ],
            [
                'tipo' => 'recarga',
                'uuid_cliente' => 'uuid-todos-recarga',
                'sesion_uuid_cliente' => 'uuid-todos-sesion',
                'secuencia' => 1,
                'litros_caldo' => '30.00',
                'bateria_saliente_id' => 'BAT-01',
                'temperatura_bateria_c' => '35.00',
                'hora' => '2026-09-01T10:25:00-04:00',
            ],
            [
                'tipo' => 'cierre_trabajo',
                'uuid_cliente' => 'uuid-todos-cierre-trabajo',
                'trabajo_uuid_cliente' => 'uuid-todos-trabajo',
                'fin' => '2026-09-01T12:00:00-04:00',
                'evidencia_imagen_campo_uuid_cliente' => $evidenciaImagenCampo,
            ],
            [
                'tipo' => 'cierre_sesion',
                'uuid_cliente' => 'uuid-todos-cierre-sesion',
                'sesion_uuid_cliente' => 'uuid-todos-sesion',
                'fin' => '2026-09-01T12:00:00-04:00',
                'motivo_cierre' => 'completado',
                'hectareas_declaradas' => '18.40',
            ],
        ],
    ])->assertOk();

    $estados = collect($respuesta->json('resultados'))->pluck('estado', 'tipo');

    expect($estados->all())->toBe([
        'trabajo' => 'aplicado',
        'recepcion_caldo' => 'aplicado',
        'sesion' => 'aplicado',
        'condiciones' => 'aplicado',
        'incidencia' => 'aplicado',
        'recarga' => 'aplicado',
        'cierre_trabajo' => 'aplicado',
        'cierre_sesion' => 'aplicado',
    ]);
});
