<?php

use App\Dominios\Metricas\Aplicacion\ArmarTableroDeMercado;
use Database\Seeders\Catalogo\UbicacionesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * El retiro de las zonas (ADR 0045, punto 1) contra el esquema real en SQLite en memoria: el catálogo
 * y el precio de referencia ya no existen, los avisos no guardan zonas y la vista de métricas agrupa por
 * departamento. Las columnas con llave foránea (`zona_id`, `zona_base_id`) se verifican en PostgreSQL
 * (subida, bajada y subida; ver el PR): SQLite no las quita sin reconstruir la tabla.
 */

uses(TestCase::class);

function zonasRetiradas(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'agrega_negociacion_a_sol_solicitudes_servicio',
        'create_sol_departamentos_table',
        'create_sol_provincias_table',
        'agrega_ubicacion_a_sol_terrenos',
        'agrega_ubicacion_a_sol_solicitudes_servicio',
        'create_neg_tablas_de_negociacion',
        'create_sol_precios_referencia_table',
        'create_sol_solicitud_historial_table',
        'create_prv_proveedores_table',
        'create_prv_pilotos_table',
        'create_prv_documentos_table',
        'create_flo_drones_table',
        'create_cal_calificaciones_table',
        'create_cal_moderaciones_table',
        'create_dsp_disputas_table',
        'create_dsp_resoluciones_table',
        'crea_vistas_de_metricas',
        'retira_zonas',
        'renombra_el_modulo_de_reclamos',
    ]);
}

function zonasSolicitud(string $estado, ?string $departamento, string $hectareas = '100.0000'): int
{
    $ahora = Carbon::now();

    return DB::table('sol_solicitudes_servicio')->insertGetId([
        'uuid_cliente' => (string) Str::uuid(), 'cliente_id' => 1, 'terreno_id' => 1, 'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => $hectareas, 'producto_indicado' => 'Glifosato', 'aporta_producto' => 'cliente',
        'ventana_desde' => '2026-12-01', 'ventana_hasta' => '2026-12-10', 'urgente' => false, 'estado' => $estado,
        'departamento_codigo' => $departamento, 'publicada_en' => $ahora, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
}

test('la migración quita el catálogo de zonas, el precio de referencia y las zonas de las preferencias', function () {
    zonasRetiradas();

    expect(Schema::hasTable('sol_zonas'))->toBeFalse()
        ->and(Schema::hasTable('sol_precios_referencia'))->toBeFalse()
        ->and(Schema::hasTable('neg_preferencias'))->toBeTrue()
        ->and(Schema::hasColumn('neg_preferencias', 'zonas'))->toBeFalse()
        ->and(Schema::hasColumn('neg_preferencias', 'macrorregiones'))->toBeFalse()
        ->and(Schema::hasColumn('neg_preferencias', 'hectareas_minimas'))->toBeTrue()
        ->and(Schema::hasColumn('prv_proveedores', 'radio_operacion_km'))->toBeFalse();
});

test('la vista de métricas agrupa por departamento: el de la solicitud, o el de su terreno si es un borrador', function () {
    zonasRetiradas();
    DB::table('sol_solicitudes_servicio')->delete();
    DB::table('sol_terrenos')->delete();
    DB::table('sol_clientes')->delete();

    $ahora = now();
    DB::table('sol_clientes')->insert(['id' => 1, 'usuario_id' => 900, 'tipo' => 'persona', 'razon_social' => 'Cliente', 'telefono' => '70011122', 'estado' => 'aprobado', 'created_at' => $ahora, 'updated_at' => $ahora]);
    DB::table('sol_terrenos')->insert(['id' => 1, 'cliente_id' => 1, 'nombre' => 'Lote', 'hectareas' => '10.0000', 'acceso_dificil' => false, 'departamento_codigo' => '03', 'created_at' => $ahora, 'updated_at' => $ahora]);

    zonasSolicitud('abierta', '07');
    zonasSolicitud('borrador', null);

    $filas = DB::table('vw_metricas_solicitudes')->orderBy('solicitud_id')->pluck('departamento_codigo')->all();
    $columnas = array_keys((array) DB::table('vw_metricas_solicitudes')->first());

    expect($filas)->toBe(['07', '03'])
        ->and($columnas)->toContain('departamento_codigo')->not->toContain('region');
});

test('el tablero del panel cuenta las solicitudes por departamento y no por región', function () {
    zonasRetiradas();
    DB::table('sol_provincias')->delete();
    DB::table('sol_departamentos')->delete();
    (new UbicacionesSeeder)->run();
    DB::table('sol_solicitudes_servicio')->delete();

    zonasSolicitud('abierta', '07', '80.0000');
    zonasSolicitud('con_ofertas', '07', '20.0000');
    zonasSolicitud('finalizada', '03', '50.0000');

    $tablero = (new ArmarTableroDeMercado)->tablero();
    $porCodigo = collect($tablero['por_departamento'])->keyBy('departamento_codigo');

    expect($tablero)->not->toHaveKey('por_region')
        ->and($porCodigo->keys()->all())->toBe(['07', '03'])
        ->and($porCodigo['07']['departamento'])->toBe('Santa Cruz')
        ->and($porCodigo['07']['abiertas'])->toBe(2)
        ->and($porCodigo['07']['hectareas'])->toBe('100.00')
        ->and($porCodigo['03']['cerradas'])->toBe(1)
        ->and($tablero['hectareas_en_mercado'])->toBe('100.00');
});
