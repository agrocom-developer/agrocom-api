<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Soporte\Esquema;
use Tests\TestCase;

/*
 * La migración que renombra el módulo de reclamos (nombres de tablas, columnas, índices y vistas, y los
 * valores que viajan en los datos) sube, baja y vuelve a subir sin perder filas. Contra el esquema real
 * de las migraciones anteriores (SQLite en memoria): los CHECK, las secuencias y las restricciones son de
 * PostgreSQL y se verificaron allí (informe del PR). Esta prueba nombra los valores anteriores a propósito:
 * son el origen del renombrado.
 */

uses(TestCase::class);

function renombreMundo(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sec_permission_table',
        'create_sec_menu_table',
        'create_ntf_notificaciones_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'agrega_negociacion_a_sol_solicitudes_servicio',
        'create_sol_solicitud_historial_table',
        'create_dsp_disputas_table',
        'create_dsp_mensajes_table',
        'create_dsp_resoluciones_table',
        'create_dsp_historial_table',
    ]);
}

function renombreFilas(): void
{
    $ahora = Carbon::now();

    DB::table('sol_solicitudes_servicio')->insert([
        'id' => 1, 'uuid_cliente' => 'a1', 'cliente_id' => 1, 'terreno_id' => 1, 'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '10.0000', 'producto_indicado' => 'x', 'aporta_producto' => 'cliente',
        'ventana_desde' => '2026-12-01', 'ventana_hasta' => '2026-12-10', 'urgente' => false, 'estado' => 'disputada',
        'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    DB::table('sol_solicitud_historial')->insert([
        'solicitud_id' => 1, 'estado_anterior' => 'finalizada', 'estado_nuevo' => 'disputada', 'origen' => 'cliente',
        'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    DB::table('dsp_disputas')->insert([
        'id' => 1, 'solicitud_id' => 1, 'cliente_id' => 1, 'cliente_usuario_id' => 9, 'abierta_por_rol' => 'cliente',
        'abierta_por_usuario_id' => 9, 'motivo' => 'cobertura_incompleta', 'detalle' => 'x', 'monto_en_disputa' => '100.00',
        'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    DB::table('dsp_mensajes')->insert(['disputa_id' => 1, 'autor_usuario_id' => 9, 'autor_rol' => 'cliente', 'tipo' => 'mensaje', 'texto' => 'x', 'created_at' => $ahora, 'updated_at' => $ahora]);
    DB::table('ntf_notificaciones')->insert([
        'usuario_id' => 9, 'tipo' => 'disputa_abierta', 'clave_evento' => 'disputa_abierta:1', 'recurso_tipo' => 'disputa', 'recurso_id' => 1,
        'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    DB::table('sec_permission')->insert([
        ['code' => 'disputas.disputa.ver', 'description' => 'x', 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        ['code' => 'solicitudes.solicitud.ver', 'description' => 'x', 'state' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
    ]);
    DB::table('sec_menu')->insert(['label' => 'menu.atencion.items.reclamos', 'icono' => 'report', 'ruta' => 'panel.disputas.index', 'orden' => 1, 'created_at' => $ahora, 'updated_at' => $ahora]);
    DB::table('plt_bitacoras')->insert(['tabla' => 'dsp_disputas', 'registro_id' => 1, 'accion' => 'creado', 'created_at' => $ahora]);
}

test('la migración renombra tablas, columnas y valores, y se revierte sin perder filas', function () {
    renombreMundo();
    renombreFilas();

    $migracion = require database_path('migrations/2026_12_19_100000_renombra_el_modulo_de_reclamos.php');

    $migracion->up();

    expect(Schema::hasTable('dsp_disputas'))->toBeFalse()
        ->and(Schema::hasTable('rcl_reclamos'))->toBeTrue()
        ->and(Schema::hasColumn('rcl_reclamos', 'monto_en_reclamo'))->toBeTrue()
        ->and(Schema::hasColumn('rcl_mensajes', 'reclamo_id'))->toBeTrue()
        ->and((float) DB::table('rcl_reclamos')->value('monto_en_reclamo'))->toBe(100.0)
        ->and(DB::table('rcl_mensajes')->where('reclamo_id', 1)->count())->toBe(1)
        ->and(DB::table('sol_solicitudes_servicio')->value('estado'))->toBe('en_reclamo')
        ->and(DB::table('sol_solicitud_historial')->value('estado_nuevo'))->toBe('en_reclamo')
        ->and(DB::table('ntf_notificaciones')->first())->toMatchObject(['tipo' => 'reclamo_abierto', 'clave_evento' => 'reclamo_abierto:1', 'recurso_tipo' => 'reclamo'])
        ->and(DB::table('sec_permission')->orderBy('code')->pluck('code')->all())->toBe(['reclamos.reclamo.ver', 'solicitudes.solicitud.ver'])
        ->and(DB::table('sec_menu')->value('ruta'))->toBe('panel.reclamos.index')
        ->and(DB::table('plt_bitacoras')->value('tabla'))->toBe('rcl_reclamos')
        ->and(DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND name = 'rcl_reclamos_solicitud_unica'"))->toHaveCount(1)
        ->and(DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND name = 'dsp_disputas_solicitud_unica'"))->toBeEmpty();

    // La unicidad parcial sigue valiendo con el nombre nuevo: un segundo reclamo vivo de la solicitud falla.
    expect(fn () => DB::table('rcl_reclamos')->insert([
        'solicitud_id' => 1, 'cliente_id' => 1, 'cliente_usuario_id' => 9, 'abierta_por_rol' => 'cliente', 'abierta_por_usuario_id' => 9,
        'motivo' => 'x', 'detalle' => 'x', 'monto_en_reclamo' => '1.00', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(Exception::class);

    $migracion->down();

    expect(Schema::hasTable('rcl_reclamos'))->toBeFalse()
        ->and(Schema::hasColumn('dsp_disputas', 'monto_en_disputa'))->toBeTrue()
        ->and(Schema::hasColumn('dsp_mensajes', 'disputa_id'))->toBeTrue()
        ->and(DB::table('sol_solicitudes_servicio')->value('estado'))->toBe('disputada')
        ->and(DB::table('ntf_notificaciones')->first())->toMatchObject(['tipo' => 'disputa_abierta', 'clave_evento' => 'disputa_abierta:1', 'recurso_tipo' => 'disputa'])
        ->and(DB::table('sec_permission')->orderBy('code')->pluck('code')->all())->toBe(['disputas.disputa.ver', 'solicitudes.solicitud.ver'])
        ->and(DB::table('sec_menu')->value('ruta'))->toBe('panel.disputas.index')
        ->and(DB::table('plt_bitacoras')->value('tabla'))->toBe('dsp_disputas');

    $migracion->up();

    expect(DB::table('rcl_reclamos')->count())->toBe(1)
        ->and(DB::table('sol_solicitudes_servicio')->value('estado'))->toBe('en_reclamo');
});
