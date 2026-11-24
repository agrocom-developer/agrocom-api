<?php

namespace App\Dominios\Metricas\Infraestructura;

use App\Dominios\Metricas\Aplicacion\CalcularMetricas;
use App\Dominios\Metricas\Contratos\LecturaTablero;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use App\Dominios\Metricas\Dominio\Razones;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lecturas agregadas del tablero (ADR 0012: `Metricas` agrega con consultas de
 * solo lectura sobre las tablas de los módulos dueños y no escribe nada). Las
 * métricas del período (tasa de adjudicación, tiempos, calificaciones) salen de
 * {@see CalcularMetricas}; acá se suma lo que es del estado de hoy. Todo importe
 * y toda hectárea en decimal exacto (invariante 6).
 */
final class LecturaTableroEloquent implements LecturaTablero
{
    private const RECIENTES = 8;

    public function __construct(private readonly CalcularMetricas $metricas) {}

    public function pendientes(): array
    {
        $contar = static fn (string $tabla, string $columna, array $valores): int => DB::table($tabla)
            ->whereNull('deleted_at')
            ->whereIn($columna, $valores)
            ->count();

        return [
            'proveedores' => $contar('prv_proveedores', 'estado', ['pendiente']),
            'pilotos' => $contar('prv_pilotos', 'estado', ['pendiente']),
            'auxiliares' => $contar('prv_auxiliares', 'estado', ['pendiente']),
            'drones' => $contar('flo_drones', 'estado', ['pendiente']),
            'documentos' => $contar('prv_documentos', 'estado', ['en_revision']),
            'fotos_dron' => $contar('flo_dron_fotos', 'estado', ['en_revision']),
            'calificaciones' => $contar('cal_calificaciones', 'estado', ['en_moderacion']),
            'disputas' => $contar('dsp_disputas', 'estado', ['abierta', 'en_revision']),
        ];
    }

    public function diasValidos(): array
    {
        return PeriodoDeMetricas::DIAS_VALIDOS;
    }

    public function tablero(?string $dias): array
    {
        $periodo = PeriodoDeMetricas::de($dias, new DateTimeImmutable);
        $desde = Carbon::instance($periodo->desde);
        $hasta = Carbon::instance($periodo->hasta);
        $base = $this->metricas->ejecutar($periodo);

        return [
            'periodo' => $base['periodo'],
            'solicitudes_por_estado' => $this->solicitudesPorEstado(),
            'mercado_por_macrorregion' => $this->mercadoPorMacrorregion(),
            'ofertas' => $this->ofertas($desde, $hasta),
            'adjudicacion' => [
                'publicadas' => $base['mercado']['solicitudes_publicadas'],
                'adjudicadas' => $base['mercado']['asignadas'],
                'tasa' => $base['mercado']['tasa_asignacion'],
                'tiempo_mediana_min' => $base['mercado']['tiempo_aceptacion_mediana_min'],
            ],
            'volumen' => $this->volumen($desde, $hasta),
            'calificaciones' => [
                'promedio' => $base['calidad']['calificacion_promedio'],
                'conteo' => $base['calidad']['calificaciones_conteo'],
                'reportadas' => $base['calidad']['calificaciones_reportadas'],
            ],
            'pendientes' => $this->pendientes(),
            'recientes' => $this->recientes(),
        ];
    }

    /** @return array<string, int> */
    private function solicitudesPorEstado(): array
    {
        return DB::table('sol_solicitudes_servicio')
            ->whereNull('deleted_at')
            ->selectRaw('estado, COUNT(*) as cantidad')
            ->groupBy('estado')
            ->pluck('cantidad', 'estado')
            ->map(static fn ($n): int => (int) $n)
            ->all();
    }

    /**
     * Demanda abierta hoy: solicitudes `abierta` y `con_ofertas` agrupadas por la
     * macrorregión de la zona de su terreno; sin zona, «sin_zona».
     *
     * @return list<array{macrorregion: string, solicitudes: int, hectareas: string}>
     */
    private function mercadoPorMacrorregion(): array
    {
        return DB::table('sol_solicitudes_servicio as s')
            ->join('sol_terrenos as t', 't.id', '=', 's.terreno_id')
            ->leftJoin('sol_zonas as z', 'z.id', '=', 't.zona_id')
            ->whereNull('s.deleted_at')
            ->whereIn('s.estado', ['abierta', 'con_ofertas'])
            ->selectRaw("COALESCE(z.macrorregion, 'sin_zona') as macrorregion, COUNT(*) as solicitudes, COALESCE(SUM(s.hectareas_solicitadas), 0) as hectareas")
            ->groupByRaw("COALESCE(z.macrorregion, 'sin_zona')")
            ->orderByDesc('solicitudes')
            ->get()
            ->map(fn (object $fila): array => [
                'macrorregion' => (string) $fila->macrorregion,
                'solicitudes' => (int) $fila->solicitudes,
                'hectareas' => (string) BigDecimal::of((string) $fila->hectareas)->toScale(2, RoundingMode::HalfUp),
            ])
            ->all();
    }

    /** @return array{vigentes: int, del_periodo: int, por_solicitud: string|null} */
    private function ofertas(Carbon $desde, Carbon $hasta): array
    {
        $delPeriodo = fn (): Builder => DB::table('neg_ofertas')->whereNull('deleted_at')->whereBetween('created_at', [$desde, $hasta]);

        $total = $delPeriodo()->count();
        $solicitudes = $delPeriodo()->distinct()->count('solicitud_id');

        return [
            'vigentes' => DB::table('neg_ofertas')->whereNull('deleted_at')->where('estado', 'vigente')->count(),
            'del_periodo' => $total,
            'por_solicitud' => $solicitudes === 0 ? null : (string) BigDecimal::of($total)->dividedBy($solicitudes, 1, RoundingMode::HalfUp),
        ];
    }

    /** @return array{hectareas_acordadas: string, monto_acordado: string, precio_acordado_promedio: string|null, precio_propuesto_promedio: string|null} */
    private function volumen(Carbon $desde, Carbon $hasta): array
    {
        $adjudicadas = fn (): Builder => DB::table('sol_solicitudes_servicio')
            ->whereNull('deleted_at')
            ->whereNotNull('adjudicada_en')
            ->whereBetween('adjudicada_en', [$desde, $hasta]);

        $filas = $adjudicadas()->get(['hectareas_solicitadas', 'monto_estimado', 'precio_ha_acordado', 'precio_ha_propuesto']);

        $promedio = static function (string $columna) use ($filas): ?string {
            $valores = $filas->pluck($columna)->filter(static fn ($v): bool => $v !== null)->values()->all();

            return Razones::cociente(Razones::suma(...$valores), (string) count($valores));
        };

        return [
            'hectareas_acordadas' => (string) BigDecimal::of(Razones::suma(...$filas->pluck('hectareas_solicitadas')->all()))->toScale(2, RoundingMode::HalfUp),
            'monto_acordado' => Razones::suma(...$filas->pluck('monto_estimado')->all()),
            'precio_acordado_promedio' => $promedio('precio_ha_acordado'),
            'precio_propuesto_promedio' => $promedio('precio_ha_propuesto'),
        ];
    }

    /** @return list<array{id: int, estado: string, cliente: string, hectareas: string, precio_ha: string|null, publicada_en: string|null}> */
    private function recientes(): array
    {
        return DB::table('sol_solicitudes_servicio as s')
            ->join('sol_clientes as c', 'c.id', '=', 's.cliente_id')
            ->whereNull('s.deleted_at')
            ->whereNotNull('s.publicada_en')
            ->orderByDesc('s.publicada_en')
            ->orderByDesc('s.id')
            ->limit(self::RECIENTES)
            ->get(['s.id', 's.estado', 'c.razon_social', 's.hectareas_solicitadas', 's.precio_ha_propuesto', 's.publicada_en'])
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'estado' => (string) $fila->estado,
                'cliente' => (string) $fila->razon_social,
                'hectareas' => (string) BigDecimal::of((string) $fila->hectareas_solicitadas)->toScale(2, RoundingMode::HalfUp),
                'precio_ha' => $fila->precio_ha_propuesto === null ? null : (string) BigDecimal::of((string) $fila->precio_ha_propuesto)->toScale(2, RoundingMode::HalfUp),
                'publicada_en' => $fila->publicada_en === null ? null : (string) $fila->publicada_en,
            ])
            ->all();
    }
}
