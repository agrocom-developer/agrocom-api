<?php

use App\Dominios\Metricas\Aplicacion\ArmarTableroDeMercado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * Tablero del panel: las solicitudes por estado y por departamento y la actividad semanal que
 * alimenta las gráficas. Contra el esquema REAL de las tablas que lee (SQLite en memoria).
 */

uses(TestCase::class);

function tableroEsquema(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sol_clientes_table',
        'create_sol_departamentos_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'agrega_negociacion_a_sol_solicitudes_servicio',
        'agrega_ubicacion_a_sol_terrenos',
        'agrega_ubicacion_a_sol_solicitudes_servicio',
        'create_prv_proveedores_table',
        'create_prv_pilotos_table',
        'create_prv_documentos_table',
        'create_flo_drones_table',
        'create_cal_calificaciones_table',
        'create_neg_tablas_de_negociacion',
        'create_dsp_disputas_table',
        'renombra_el_modulo_de_reclamos',
    ]);

    DB::table('sol_solicitudes_servicio')->delete();
}

/** @param  array<string, mixed>  $atributos */
function tableroSolicitud(string $estado, Carbon $publicada, array $atributos = []): void
{
    DB::table('sol_solicitudes_servicio')->insert([
        'uuid_cliente' => (string) Str::uuid(), 'cliente_id' => 1, 'terreno_id' => 1, 'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '100.0000', 'producto_indicado' => 'Glifosato', 'aporta_producto' => 'cliente',
        'ventana_desde' => '2026-12-01', 'ventana_hasta' => '2026-12-10', 'urgente' => false, 'estado' => $estado, 'publicada_en' => $publicada, 'created_at' => $publicada, 'updated_at' => $publicada,
        ...$atributos,
    ]);
}

test('el tablero da doce semanas de actividad, también las vacías, con el precio medio ponderado por hectáreas', function () {
    tableroEsquema();
    $esta = Carbon::now()->startOfWeek()->addHours(12);

    tableroSolicitud('adjudicada', $esta->copy()->subWeeks(2), ['adjudicada_en' => $esta, 'precio_ha_acordado' => '100.0000', 'hectareas_solicitadas' => '100.0000']);
    tableroSolicitud('adjudicada', $esta->copy()->subWeeks(2), ['adjudicada_en' => $esta, 'precio_ha_acordado' => '130.0000', 'hectareas_solicitadas' => '300.0000']);
    tableroSolicitud('abierta', $esta->copy()->subWeeks(20));

    $semanas = app(ArmarTableroDeMercado::class)->tablero()['por_semana'];

    expect($semanas)->toHaveCount(12)
        ->and($semanas[11])->toBe(['semana' => $esta->copy()->startOfWeek()->toDateString(), 'publicadas' => 0, 'adjudicadas' => 2, 'precio_ha_medio' => '122.50'])
        ->and($semanas[9]['publicadas'])->toBe(2)
        ->and($semanas[9]['precio_ha_medio'])->toBeNull()
        ->and($semanas[0]['publicadas'])->toBe(0)
        ->and(array_sum(array_column($semanas, 'publicadas')))->toBe(2);
});

test('una solicitud expirada cuenta con las canceladas del departamento', function () {
    tableroEsquema();
    tableroSolicitud('expirada', Carbon::now());

    $tablero = app(ArmarTableroDeMercado::class)->tablero();

    expect($tablero['total_solicitudes'])->toBe(1)
        ->and($tablero['por_departamento'][0]['canceladas'])->toBe(1);
});
