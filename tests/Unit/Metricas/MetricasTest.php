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
 * Métricas del mercado (especificación §14): solicitudes, asignación, tasa de
 * aceptación, calificación, disputas, tiempo hasta la aceptación e ingreso, en
 * decimal exacto y con «sin datos» (`null`) donde no hay divisor. Contra el ESQUEMA
 * REAL de las tablas que lee (las migraciones, en SQLite en memoria).
 */

uses(TestCase::class);

function metricasEsquema(): void
{
    Esquema::migrar([
        'create_plt_bitacoras_table',
        'create_sol_zonas_table',
        'create_sol_clientes_table',
        'create_sol_terrenos_table',
        'create_sol_solicitudes_servicio_table',
        'create_sol_solicitud_historial_table',
        'create_prv_proveedores_table',
        'create_flo_drones_table',
        'create_asg_rondas_table',
        'create_asg_ofertas_table',
        'create_asg_cancelaciones_proveedor_table',
        'create_asg_sanciones_table',
        'create_cal_calificaciones_table',
        'create_cal_moderaciones_table',
        'create_dsp_disputas_table',
        'create_dsp_resoluciones_table',
    ]);

    foreach (['dsp_resoluciones', 'dsp_disputas', 'cal_calificaciones', 'asg_sanciones', 'asg_cancelaciones_proveedor', 'asg_ofertas', 'sol_solicitud_historial', 'sol_solicitudes_servicio', 'flo_drones', 'prv_proveedores'] as $tabla) {
        DB::table($tabla)->delete();
    }

    app()->instance(LecturaProveedores::class, new ProveedoresFalsos([
        7 => new DatosProveedor(7, 700, 'tercero', 'Proveedor Siete', 'aprobado', calificacionPromedio: '4.50', calificacionesConteo: 12, tasaAceptacion: '0.8000'),
        1 => new DatosProveedor(1, 100, 'propio', 'Agrocom', 'aprobado'),
    ]));
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

function metricasOferta(int $solicitud, int $proveedor, string $grupo, string $estado, Carbon $ofrecida, ?Carbon $respondida, int $ciclo = 1): void
{
    DB::table('asg_ofertas')->insert([
        'solicitud_id' => $solicitud, 'proveedor_id' => $proveedor, 'ciclo' => $ciclo, 'ronda' => 1, 'grupo' => $grupo, 'origen' => 'motor',
        'estado' => $estado, 'ofrecida_en' => $ofrecida, 'vence_en' => $ofrecida->copy()->addHours(2), 'respondida_en' => $respondida,
        'created_at' => $ofrecida, 'updated_at' => $ofrecida,
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
        ->and($m['mercado']['tasa_asignacion'])->toBeNull()
        ->and($m['mercado']['tiempo_aceptacion_promedio_min'])->toBeNull()
        ->and($m['calidad']['calificacion_promedio'])->toBeNull()
        ->and($m['calidad']['tasa_disputas'])->toBeNull()
        ->and($m['oferta']['tasa_aceptacion'])->toBeNull()
        ->and($m['ingreso']['volumen_bruto'])->toBe('0.00')
        ->and($m['ingreso']['ingreso_por_hectarea'])->toBeNull()
        ->and($m['proveedores'])->toBe([]);
});

test('el mercado cuenta lo publicado en el período, la asignación, los tiempos y propios contra terceros', function () {
    metricasEsquema();
    $hace = fn (int $dias): Carbon => Carbon::now()->subDays($dias);

    $a = metricasSolicitud('completada', 7, $hace(5));
    $b = metricasSolicitud('completada', 1, $hace(4), ['hectareas_solicitadas' => '50.0000']);
    metricasSolicitud('sin_proveedor', null, $hace(3));
    metricasSolicitud('en_asignacion', null, $hace(2));
    metricasSolicitud('completada', 7, $hace(60));

    metricasOferta($a, 7, 'tercero', 'aceptada', $hace(5), $hace(5)->addMinutes(30));
    metricasOferta($b, 1, 'propio', 'aceptada', $hace(4), $hace(4)->addMinutes(10));

    $m = app(CalcularMetricas::class)->ejecutar(PeriodoDeMetricas::de('30', new DateTimeImmutable))['mercado'];

    expect($m['solicitudes_publicadas'])->toBe(4)
        ->and($m['por_estado'])->toEqual(['completada' => 2, 'sin_proveedor' => 1, 'en_asignacion' => 1])
        ->and($m['asignadas'])->toBe(2)
        ->and($m['tasa_asignacion'])->toBe('0.5000')
        ->and($m['sin_proveedor'])->toBe(1)
        ->and($m['hectareas_solicitadas'])->toBe('350.0000')
        ->and($m['hectareas_servidas'])->toBe('150.0000')
        ->and($m['tiempo_aceptacion_promedio_min'])->toBe('20.0')
        ->and($m['tiempo_aceptacion_mediana_min'])->toBe('20.0')
        ->and($m['aceptadas_por_propios'])->toBe(1)
        ->and($m['aceptadas_por_terceros'])->toBe(1)
        ->and($m['proporcion_propios'])->toBe('0.5000');

    $m7 = app(CalcularMetricas::class)->ejecutar(PeriodoDeMetricas::de('7', new DateTimeImmutable))['mercado'];
    expect($m7['solicitudes_publicadas'])->toBe(4);
    $m90 = app(CalcularMetricas::class)->ejecutar(PeriodoDeMetricas::de('90', new DateTimeImmutable))['mercado'];
    expect($m90['solicitudes_publicadas'])->toBe(5);
});

test('la calidad promedia lo visible, calcula las tasas de disputa y de reembolso y toma la última resolución', function () {
    metricasEsquema();
    $hace = fn (int $dias): Carbon => Carbon::now()->subDays($dias);

    $a = metricasSolicitud('completada', 7, $hace(5), ['monto_final' => '12000.00', 'comision_monto' => '1800.00']);
    $b = metricasSolicitud('completada', 1, $hace(4), ['monto_final' => '6000.00', 'comision_monto' => '0.00', 'hectareas_solicitadas' => '50.0000']);
    foreach ([$a, $b] as $id) {
        metricasHistorial($id, 'ejecutada', $hace(4));
        metricasHistorial($id, 'completada', $hace(3));
    }

    foreach ([[5, 'visible'], [4, 'visible'], [1, 'oculta']] as $i => [$estrellas, $estado]) {
        DB::table('cal_calificaciones')->insert([
            'uuid_cliente' => (string) Str::uuid(), 'solicitud_id' => $a, 'proveedor_id' => 7, 'cliente_id' => 1, 'autor_usuario_id' => 9, 'autor_rol' => 'cliente',
            'sujeto_tipo' => 'proveedor', 'sujeto_id' => 7 + $i, 'estrellas' => $estrellas, 'etiquetas' => '[]', 'estado' => $estado,
            'created_at' => $hace(2), 'updated_at' => $hace(2),
        ]);
    }

    $disputa = DB::table('dsp_disputas')->insertGetId([
        'solicitud_id' => $a, 'cliente_id' => 1, 'cliente_usuario_id' => 900, 'proveedor_id' => 7, 'abierta_por_rol' => 'cliente', 'abierta_por_usuario_id' => 900,
        'motivo' => 'cobertura_incompleta', 'detalle' => 'Faltó cubrir', 'monto_en_disputa' => '12000.00', 'estado' => 'resuelta', 'ronda' => 2,
        'created_at' => $hace(3), 'updated_at' => $hace(1),
    ]);
    foreach ([[1, '3000.00', '450.00'], [2, '2000.00', '300.00']] as [$ronda, $reembolso, $ajuste]) {
        DB::table('dsp_resoluciones')->insert([
            'disputa_id' => $disputa, 'ronda' => $ronda, 'resultado' => 'parcial', 'monto_reembolso' => $reembolso, 'comision_ajuste' => $ajuste,
            'motivo' => 'Ronda '.$ronda, 'resuelta_por' => 5, 'resuelta_en' => $hace(2), 'created_at' => $hace(2), 'updated_at' => $hace(2),
        ]);
    }

    $m = app(CalcularMetricas::class)->ejecutar(PeriodoDeMetricas::de('30', new DateTimeImmutable));

    expect($m['calidad']['calificacion_promedio'])->toBe('4.50')
        ->and($m['calidad']['calificaciones_conteo'])->toBe(2)
        ->and($m['calidad']['servicios_ejecutados'])->toBe(2)
        ->and($m['calidad']['disputas_abiertas'])->toBe(1)
        ->and($m['calidad']['tasa_disputas'])->toBe('0.5000')
        ->and($m['calidad']['disputas_por_estado'])->toBe(['resuelta' => 1])
        ->and($m['calidad']['monto_reembolsado'])->toBe('2000.00')
        ->and($m['calidad']['tasa_reembolso'])->toBe('0.1111')
        ->and($m['ingreso']['servicios_completados'])->toBe(2)
        ->and($m['ingreso']['volumen_bruto'])->toBe('18000.00')
        ->and($m['ingreso']['comision_congelada'])->toBe('1800.00')
        ->and($m['ingreso']['comision_ajustada_por_disputas'])->toBe('300.00')
        ->and($m['ingreso']['comision_devengada'])->toBe('1500.00')
        ->and($m['ingreso']['ingreso_por_hectarea'])->toBe('10.00');
});

test('la oferta calcula la tasa de aceptación y de cancelación y la tabla ordena por servicios', function () {
    metricasEsquema();
    $hace = fn (int $dias): Carbon => Carbon::now()->subDays($dias);

    $a = metricasSolicitud('completada', 7, $hace(5));
    $b = metricasSolicitud('completada', 7, $hace(4));
    $c = metricasSolicitud('completada', 1, $hace(3));
    foreach ([$a, $b, $c] as $id) {
        metricasHistorial($id, 'completada', $hace(2));
    }

    metricasOferta($a, 7, 'tercero', 'aceptada', $hace(5), $hace(5)->addMinutes(20));
    metricasOferta($b, 7, 'tercero', 'aceptada', $hace(4), $hace(4)->addMinutes(40));
    metricasOferta($c, 7, 'tercero', 'rechazada', $hace(3), $hace(3)->addMinutes(5));
    metricasOferta($c, 7, 'tercero', 'vencida', $hace(3), null, 2);
    metricasOferta($c, 1, 'propio', 'aceptada', $hace(3), $hace(3)->addMinutes(2));

    DB::table('asg_cancelaciones_proveedor')->insert([
        'solicitud_id' => $a, 'proveedor_id' => 7, 'causa' => 'otra', 'motivo' => 'Falla', 'peso' => 1, 'cancelada_en' => $hace(2), 'created_at' => $hace(2), 'updated_at' => $hace(2),
    ]);
    DB::table('prv_proveedores')->insert(['id' => 7, 'tipo' => 'tercero', 'razon_social' => 'Proveedor Siete', 'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('prv_proveedores')->insert(['id' => 8, 'tipo' => 'tercero', 'razon_social' => 'Proveedor Ocho', 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now()]);

    $m = app(CalcularMetricas::class)->ejecutar(PeriodoDeMetricas::de('30', new DateTimeImmutable));

    expect($m['oferta']['proveedores_aprobados'])->toBe(1)
        ->and($m['oferta']['proveedores_pendientes'])->toBe(1)
        ->and($m['oferta']['ofertas_respondidas'])->toBe(5)
        ->and($m['oferta']['tasa_aceptacion'])->toBe('0.6000')
        ->and($m['oferta']['cancelaciones_proveedor'])->toBe(1)
        ->and($m['oferta']['tasa_cancelacion'])->toBe('0.3333')
        ->and(array_column($m['proveedores'], 'proveedor_id'))->toBe([7, 1])
        ->and($m['proveedores'][0])->toMatchArray([
            'nombre' => 'Proveedor Siete', 'servicios' => 2, 'calificacion_promedio' => '4.50', 'calificaciones_conteo' => 12,
            'tasa_aceptacion' => '0.8000', 'respuesta_promedio_min' => '21.7',
        ]);
});
