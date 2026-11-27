<?php

use App\Dominios\Metricas\Aplicacion\CalcularMetricas;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use App\Dominios\Metricas\Dominio\Razones;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Soporte\Esquema;
use Tests\Soporte\ProveedoresFalsos;
use Tests\TestCase;

/*
 * Métricas del mercado (especificación §14): solicitudes, adjudicación, tasa de aceptación,
 * calificación, reclamos, tiempo hasta la adjudicación y volumen, en decimal exacto y con
 * «sin datos» (`null`) donde no hay divisor. Sin comisión: el cobro no pasa por la app. Contra
 * el ESQUEMA REAL de las tablas que lee (las migraciones, en SQLite en memoria).
 */

uses(TestCase::class);

function metricasEsquema(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_departamentos_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'agrega_negociacion_a_sol_solicitudes_servicio',
        'agrega_ubicacion_a_sol_terrenos',
        'agrega_ubicacion_a_sol_solicitudes_servicio',
        'create_neg_tablas_de_negociacion',
        'create_sol_solicitud_historial_table',
        'create_prv_proveedores_table',
        'create_flo_drones_table',
        'create_cal_calificaciones_table',
        'create_cal_moderaciones_table',
        'create_dsp_disputas_table',
        'create_dsp_resoluciones_table',
        'crea_vistas_de_metricas',
        'retira_zonas',
        'renombra_el_modulo_de_reclamos',
    ]);

    foreach (['rcl_resoluciones', 'rcl_reclamos', 'cal_calificaciones', 'neg_cancelaciones', 'neg_ofertas', 'sol_solicitud_historial', 'sol_solicitudes_servicio', 'flo_drones', 'prv_proveedores'] as $tabla) {
        DB::table($tabla)->delete();
    }

    app()->instance(LecturaProveedores::class, new ProveedoresFalsos([
        7 => new DatosProveedor(7, 700, 'tercero', 'Proveedor Siete', 'aprobado', calificacionPromedio: '4.50', calificacionesConteo: 12),
        1 => new DatosProveedor(1, 100, 'propio', 'Agrocom', 'aprobado'),
    ]));

    DB::table('prv_proveedores')->insert(['id' => 7, 'tipo' => 'tercero', 'razon_social' => 'Proveedor Siete', 'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('prv_proveedores')->insert(['id' => 1, 'tipo' => 'propio', 'razon_social' => 'Agrocom', 'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('prv_proveedores')->insert(['id' => 8, 'tipo' => 'tercero', 'razon_social' => 'Proveedor Ocho', 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now()]);
}

/** @param  array<string, mixed>  $atributos */
function metricasSolicitud(string $estado, ?int $proveedor, Carbon $publicada, array $atributos = []): int
{
    return DB::table('sol_solicitudes_servicio')->insertGetId([
        'uuid_cliente' => (string) Str::uuid(), 'cliente_id' => 1, 'terreno_id' => 1, 'tipo_aplicacion' => 'herbicida',
        'hectareas_solicitadas' => '100.0000', 'producto_indicado' => 'Glifosato', 'aporta_producto' => 'cliente',
        'ventana_desde' => '2026-12-01', 'ventana_hasta' => '2026-12-10', 'urgente' => false,
        'estado' => $estado, 'proveedor_id' => $proveedor, 'publicada_en' => $publicada, 'created_at' => $publicada, 'updated_at' => $publicada,
        ...$atributos,
    ]);
}

function metricasHistorial(int $solicitud, string $estado, Carbon $cuando): void
{
    DB::table('sol_solicitud_historial')->insert(['solicitud_id' => $solicitud, 'estado_nuevo' => $estado, 'origen' => 'sistema', 'created_at' => $cuando, 'updated_at' => $cuando]);
}

function metricasOferta(int $solicitud, int $proveedor, string $estado, Carbon $creada, ?Carbon $respondida, string $autor = 'proveedor', int $numero = 1): int
{
    return DB::table('neg_ofertas')->insertGetId([
        'uuid_cliente' => (string) Str::uuid(), 'solicitud_id' => $solicitud, 'proveedor_id' => $proveedor, 'numero' => $numero, 'autor' => $autor,
        'tipo' => 'acepta_propuesta', 'precio_ha' => '120.0000', 'monto_estimado' => '12000.00', 'estado' => $estado,
        'vence_en' => $creada->copy()->addHours(48), 'respondida_en' => $respondida, 'created_at' => $creada, 'updated_at' => $creada,
    ]);
}

// --- Dominio puro ---------------------------------------------------------

test('el período son los últimos 7, 30 o 90 días y cualquier otro valor cae en 30', function () {
    $ahora = new DateTimeImmutable('2026-12-31 12:00:00');

    expect(PeriodoDeMetricas::de('7', $ahora)->dias)->toBe(7)
        ->and(PeriodoDeMetricas::de('90', $ahora)->desde->format('Y-m-d'))->toBe('2026-10-02')
        ->and(PeriodoDeMetricas::de(null, $ahora)->dias)->toBe(30)
        ->and(PeriodoDeMetricas::de('15', $ahora)->dias)->toBe(30)
        ->and(PeriodoDeMetricas::de('30', $ahora)->hasta)->toEqual($ahora);
});

test('una tasa sin divisor es null y no cero, y las cuentas son exactas', function () {
    expect(Razones::tasa(1, 3))->toBe('0.3333')
        ->and(Razones::tasa(0, 5))->toBe('0.0000')
        ->and(Razones::tasa(3, 0))->toBeNull()
        ->and(Razones::tasaDeImportes('2000.00', '18000.00'))->toBe('0.1111')
        ->and(Razones::tasaDeImportes('1.00', '0.00'))->toBeNull()
        ->and(Razones::cociente('1500.00', '150.00'))->toBe('10.00')
        ->and(Razones::cociente('1.00', '0'))->toBeNull()
        ->and(Razones::promedio([30, 10]))->toBe('20.0')
        ->and(Razones::promedio([]))->toBeNull()
        ->and(Razones::mediana([5, 1, 3]))->toBe('3.0')
        ->and(Razones::mediana([1, 2, 3, 4]))->toBe('2.5')
        ->and(Razones::mediana([]))->toBeNull()
        ->and(Razones::suma('0.10', '0.20', null, ''))->toBe('0.30');
});

// --- Con datos ------------------------------------------------------------

test('sin datos las tasas son null y las sumas cero', function () {
    metricasEsquema();

    $m = app(CalcularMetricas::class)->ejecutar(PeriodoDeMetricas::de('30', new DateTimeImmutable));

    expect($m['mercado']['solicitudes_publicadas'])->toBe(0)
        ->and($m['mercado']['tasa_adjudicacion'])->toBeNull()
        ->and($m['mercado']['tiempo_aceptacion_promedio_min'])->toBeNull()
        ->and($m['calidad']['calificacion_promedio'])->toBeNull()
        ->and($m['calidad']['tasa_reclamos'])->toBeNull()
        ->and($m['oferta']['tasa_aceptacion'])->toBeNull()
        ->and($m['ingreso']['volumen_bruto'])->toBe('0.00')
        ->and($m['ingreso']['ingreso_por_hectarea'])->toBeNull()
        ->and($m['proveedores'])->toBe([]);
});

test('el mercado cuenta lo publicado en el período, la adjudicación, los tiempos y las rondas de negociación', function () {
    metricasEsquema();
    $ahora = Carbon::now();
    $hace = fn (int $dias): Carbon => $ahora->copy()->subDays($dias);

    $a = metricasSolicitud('finalizada', 7, $hace(5), ['hectareas_ejecutadas' => '90.0000', 'adjudicada_en' => $hace(5)->addMinutes(180), 'departamento_codigo' => '07']);
    $b = metricasSolicitud('adjudicada', 1, $hace(4), ['adjudicada_en' => $hace(4)->addMinutes(60), 'departamento_codigo' => '07']);
    metricasSolicitud('con_ofertas', null, $hace(3), ['departamento_codigo' => '03']);
    metricasSolicitud('abierta', null, $hace(2));
    metricasSolicitud('finalizada', 7, $hace(60), ['adjudicada_en' => $hace(60)->addMinutes(10)]);

    $oa = metricasOferta($a, 7, 'aceptada', $hace(5), $hace(5)->addMinutes(180));
    $ob = metricasOferta($b, 1, 'aceptada', $hace(4), $hace(4)->addMinutes(60));
    DB::table('sol_solicitudes_servicio')->where('id', $a)->update(['oferta_adjudicada_id' => $oa]);
    DB::table('sol_solicitudes_servicio')->where('id', $b)->update(['oferta_adjudicada_id' => $ob]);

    $m = app(CalcularMetricas::class)->ejecutar(PeriodoDeMetricas::de('30', $ahora->toDateTimeImmutable()))['mercado'];

    expect($m['solicitudes_publicadas'])->toBe(4)
        ->and($m['por_estado'])->toEqual(['finalizada' => 1, 'adjudicada' => 1, 'con_ofertas' => 1, 'abierta' => 1])
        ->and($m['por_departamento'])->toEqual(['07' => 2, '03' => 1, 'sin_departamento' => 1])
        ->and($m)->not->toHaveKey('por_region')
        ->and($m['adjudicadas'])->toBe(2)
        ->and($m['tasa_adjudicacion'])->toBe('0.5000')
        ->and($m['abiertas'])->toBe(2)
        ->and($m['hectareas_solicitadas'])->toBe('400.0000')
        ->and($m['hectareas_servidas'])->toBe('90.0000')
        ->and($m['aceptaciones'])->toBe(2)
        ->and($m['tiempo_aceptacion_promedio_min'])->toBe('120.0')
        ->and($m['tiempo_aceptacion_mediana_min'])->toBe('120.0')
        ->and($m['ofertas_por_solicitud'])->toBe('0.50')
        ->and($m['rondas_promedio'])->not->toBeNull()
        ->and($m['negociaciones'])->toBe(2);
});

test('el embudo, las distribuciones y los departamentos salen de lo publicado en el período', function () {
    metricasEsquema();
    DB::table('sol_departamentos')->insert(['codigo' => '07', 'nombre' => 'Santa Cruz']);
    $ahora = Carbon::now();
    $hace = fn (int $dias): Carbon => $ahora->copy()->subDays($dias);

    $a = metricasSolicitud('finalizada', 7, $hace(5), ['adjudicada_en' => $hace(5)->addMinutes(30), 'departamento_codigo' => '07']);
    $b = metricasSolicitud('adjudicada', 1, $hace(4), ['adjudicada_en' => $hace(4)->addMinutes(200), 'departamento_codigo' => '07']);
    $c = metricasSolicitud('con_ofertas', null, $hace(3), ['departamento_codigo' => '07']);
    metricasSolicitud('cancelada', null, $hace(2));
    $oa = metricasOferta($a, 7, 'aceptada', $hace(5), $hace(5)->addMinutes(30));
    $ob = metricasOferta($b, 1, 'aceptada', $hace(4), $hace(4)->addMinutes(200), numero: 3);
    DB::table('sol_solicitudes_servicio')->where('id', $a)->update(['oferta_adjudicada_id' => $oa]);
    DB::table('sol_solicitudes_servicio')->where('id', $b)->update(['oferta_adjudicada_id' => $ob]);
    metricasOferta($c, 7, 'vigente', $hace(3), null);

    $m = app(CalcularMetricas::class)->ejecutar(PeriodoDeMetricas::de('30', $ahora->toDateTimeImmutable()))['mercado'];

    expect($m['embudo'])->toBe(['publicadas' => 4, 'con_ofertas' => 3, 'adjudicadas' => 2, 'finalizadas' => 1])
        ->and($m['distribucion_tiempo'])->toBe([1, 1, 0, 0, 0])
        ->and($m['distribucion_rondas'])->toBe([2, 0, 1, 0])
        ->and($m['departamentos'])->toBe([
            ['codigo' => '07', 'nombre' => 'Santa Cruz', 'abiertas' => 1, 'en_curso' => 1, 'cerradas' => 1, 'canceladas' => 0, 'total' => 3],
            ['codigo' => 'sin_departamento', 'nombre' => null, 'abiertas' => 0, 'en_curso' => 0, 'cerradas' => 0, 'canceladas' => 1, 'total' => 1],
        ]);
});

test('distribuir reparte los valores en tramos de extremo superior exclusivo', function () {
    expect(Razones::distribuir([0, 59.9, 60, 100, 360, 9999], [60, 360]))->toBe([2, 2, 2])
        ->and(Razones::distribuir([], [60, 360]))->toBe([0, 0, 0]);
});

test('la oferta calcula la tasa de aceptación y de cancelación del proveedor', function () {
    metricasEsquema();
    $ahora = Carbon::now();

    metricasOferta(1, 7, 'aceptada', $ahora->copy()->subDay(), $ahora);
    metricasOferta(2, 7, 'reemplazada', $ahora->copy()->subDay(), $ahora);
    metricasOferta(3, 7, 'rechazada', $ahora->copy()->subDay(), $ahora);
    metricasOferta(4, 7, 'vencida', $ahora->copy()->subDay(), null);
    DB::table('neg_cancelaciones')->insert(['solicitud_id' => 1, 'proveedor_id' => 7, 'parte' => 'proveedor', 'motivo' => 'Dron en mantenimiento', 'created_at' => $ahora, 'updated_at' => $ahora]);

    $m = app(CalcularMetricas::class)->ejecutar(PeriodoDeMetricas::de('30', $ahora->toDateTimeImmutable()))['oferta'];

    expect($m['proveedores_aprobados'])->toBe(2)
        ->and($m['proveedores_pendientes'])->toBe(1)
        ->and($m['ofertas_respondidas'])->toBe(4)
        ->and($m['tasa_aceptacion'])->toBe('0.5000')
        ->and($m['cancelaciones_proveedor'])->toBe(1)
        ->and($m['tasa_cancelacion'])->toBe('0.5000');
});

test('el volumen y el ingreso por hectárea salen de lo finalizado, sin comisión', function () {
    metricasEsquema();
    $ahora = Carbon::now();

    $a = metricasSolicitud('finalizada', 7, $ahora->copy()->subDays(5), ['monto_final' => '12000.00', 'hectareas_ejecutadas' => '100.0000']);
    $b = metricasSolicitud('calificada', 1, $ahora->copy()->subDays(4), ['monto_final' => '6000.00', 'hectareas_ejecutadas' => '50.0000']);
    foreach ([$a, $b] as $id) {
        metricasHistorial($id, 'finalizada', $ahora->copy()->subDays(3));
    }

    $m = app(CalcularMetricas::class)->ejecutar(PeriodoDeMetricas::de('30', $ahora->toDateTimeImmutable()));

    expect($m['ingreso']['servicios_completados'])->toBe(2)
        ->and($m['ingreso']['volumen_bruto'])->toBe('18000.00')
        ->and($m['ingreso']['ingreso_por_hectarea'])->toBe('120.00')
        ->and($m['ingreso'])->not->toHaveKey('comision_devengada')
        ->and($m['calidad']['servicios_ejecutados'])->toBe(2)
        ->and(array_column($m['proveedores'], 'servicios', 'proveedor_id'))->toEqual([7 => 1, 1 => 1]);
});
