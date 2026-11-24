<?php

namespace App\Dominios\Metricas\Aplicacion;

use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use App\Dominios\Metricas\Dominio\Razones;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Las métricas del mercado del panel (especificación §14): mercado (solicitudes,
 * asignación y tiempo hasta la aceptación), calidad (calificación, disputas y
 * reembolsos), oferta (proveedores, drones, tasa de aceptación y de cancelación) e
 * volumen (monto acordado de los servicios finalizados). Módulo de solo lectura (ADR 0012): agrega
 * con consultas de lectura sobre las tablas de los módulos dueños y no escribe
 * nada; los nombres y las tasas de cada proveedor llegan por el contrato de
 * `Proveedores`. Todo importe y toda tasa en decimal exacto (invariante 6 de
 * CLAUDE.md); sin divisor, `null` («sin datos»), nunca cero.
 *
 * No hay cobro en la aplicación: el monto final es hectáreas ejecutadas por precio
 * acordado y las partes lo pagan fuera de la app.
 */
final class CalcularMetricas
{
    /** Filas que se leen para promediar tiempos: acota la memoria de un período con mucho movimiento. */
    private const LIMITE_FILAS = 5000;

    private const PROVEEDORES_EN_TABLA = 10;

    public function __construct(private readonly LecturaProveedores $proveedores) {}

    /**
     * @return array{
     *     periodo: array{dias: int, desde: string, hasta: string},
     *     mercado: array<string, mixed>,
     *     calidad: array<string, mixed>,
     *     oferta: array<string, mixed>,
     *     ingreso: array<string, mixed>,
     *     proveedores: list<array<string, mixed>>
     * }
     */
    public function ejecutar(PeriodoDeMetricas $periodo): array
    {
        $desde = Carbon::instance($periodo->desde);
        $hasta = Carbon::instance($periodo->hasta);

        return [
            'periodo' => ['dias' => $periodo->dias, 'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()],
            'mercado' => $this->mercado($desde, $hasta),
            'calidad' => $this->calidad($desde, $hasta),
            'oferta' => $this->oferta($desde, $hasta),
            'ingreso' => $this->ingreso($desde, $hasta),
            'proveedores' => $this->porProveedor($desde, $hasta),
        ];
    }

    /** @return array<string, mixed> */
    private function mercado(Carbon $desde, Carbon $hasta): array
    {
        $publicadas = fn (): Builder => DB::table('sol_solicitudes_servicio')->whereNull('deleted_at')->whereBetween('publicada_en', [$desde, $hasta]);

        $total = $publicadas()->count();
        $asignadas = $publicadas()->whereNotNull('proveedor_id')->count();
        $porEstado = $publicadas()->selectRaw('estado, COUNT(*) as cantidad')->groupBy('estado')->pluck('cantidad', 'estado')->map(fn ($n): int => (int) $n)->all();

        $aceptadas = DB::table('neg_ofertas as o')
            ->join('sol_solicitudes_servicio as s', 's.id', '=', 'o.solicitud_id')
            ->join('prv_proveedores as p', 'p.id', '=', 'o.proveedor_id')
            ->whereNull('o.deleted_at')
            ->whereIn('o.estado', ['aceptada', 'cerrada'])
            ->whereNotNull('s.publicada_en')
            ->whereNotNull('s.adjudicada_en')
            ->whereBetween('s.adjudicada_en', [$desde, $hasta])
            ->where('o.id', DB::raw('s.oferta_adjudicada_id'))
            ->orderBy('o.id')
            ->limit(self::LIMITE_FILAS)
            ->get(['s.adjudicada_en as respondida_en', 'p.tipo', 's.publicada_en']);

        $minutos = $aceptadas
            ->map(fn ($fila): float => max(0, Carbon::parse($fila->publicada_en)->diffInSeconds(Carbon::parse($fila->respondida_en), false)) / 60)
            ->values()
            ->all();

        $aPropios = $aceptadas->where('tipo', 'propio')->count();

        return [
            'solicitudes_publicadas' => $total,
            'por_estado' => $porEstado,
            'asignadas' => $asignadas,
            'tasa_asignacion' => Razones::tasa($asignadas, $total),
            'sin_proveedor' => (int) ($porEstado['expirada'] ?? 0),
            'hectareas_solicitadas' => $this->suma($publicadas(), 'hectareas_solicitadas', 4),
            'hectareas_servidas' => $this->suma($publicadas()->whereIn('estado', ['finalizada', 'calificada']), 'hectareas_ejecutadas', 4),
            'tiempo_aceptacion_promedio_min' => Razones::promedio($minutos),
            'tiempo_aceptacion_mediana_min' => Razones::mediana($minutos),
            'aceptaciones' => $aceptadas->count(),
            'aceptadas_por_propios' => $aPropios,
            'aceptadas_por_terceros' => $aceptadas->count() - $aPropios,
            'proporcion_propios' => Razones::tasa($aPropios, $aceptadas->count()),
        ];
    }

    /** @return array<string, mixed> */
    private function calidad(Carbon $desde, Carbon $hasta): array
    {
        $ejecutadas = DB::table('sol_solicitud_historial')->whereNull('deleted_at')->where('estado_nuevo', 'finalizada')->whereBetween('created_at', [$desde, $hasta])->distinct()->count('solicitud_id');
        $completadas = DB::table('sol_solicitud_historial')->whereNull('deleted_at')->where('estado_nuevo', 'finalizada')->whereBetween('created_at', [$desde, $hasta])->pluck('solicitud_id')->unique()->all();

        $calificaciones = DB::table('cal_calificaciones')
            ->whereNull('deleted_at')
            ->where('autor_rol', 'cliente')
            ->where('sujeto_tipo', 'proveedor')
            ->where('estado', '!=', 'oculta')
            ->whereBetween('created_at', [$desde, $hasta]);
        $conteoCalificaciones = (clone $calificaciones)->count();
        $sumaEstrellas = (int) (clone $calificaciones)->sum('estrellas');

        $reportadas = DB::table('cal_calificaciones')->whereNull('deleted_at')->where('estado', 'en_moderacion')->count();

        $disputas = DB::table('dsp_disputas')->whereNull('deleted_at')->whereBetween('created_at', [$desde, $hasta]);
        $abiertas = (clone $disputas)->count();
        $disputasPorEstado = (clone $disputas)->selectRaw('estado, COUNT(*) as cantidad')->groupBy('estado')->pluck('cantidad', 'estado')->map(fn ($n): int => (int) $n)->all();

        $reembolsado = $this->reembolsos($desde, $hasta);
        $montoCompletadas = $completadas === [] ? '0.00' : Razones::suma(...DB::table('sol_solicitudes_servicio')->whereNull('deleted_at')->whereIn('id', $completadas)->pluck('monto_final')->all());

        return [
            'calificacion_promedio' => $conteoCalificaciones === 0 ? null : (string) BigDecimal::of($sumaEstrellas)->dividedBy($conteoCalificaciones, 2, RoundingMode::HalfUp),
            'calificaciones_conteo' => $conteoCalificaciones,
            'calificaciones_reportadas' => $reportadas,
            'servicios_ejecutados' => $ejecutadas,
            'disputas_abiertas' => $abiertas,
            'disputas_por_estado' => $disputasPorEstado,
            'tasa_disputas' => Razones::tasa($abiertas, $ejecutadas),
            'monto_reembolsado' => $reembolsado['monto'],
            'tasa_reembolso' => Razones::tasaDeImportes($reembolsado['monto'], $montoCompletadas),
        ];
    }

    /** @return array<string, mixed> */
    private function oferta(Carbon $desde, Carbon $hasta): array
    {
        $ofertas = fn (): Builder => DB::table('neg_ofertas')->whereNull('deleted_at')->where('autor', 'proveedor')->whereBetween('created_at', [$desde, $hasta]);

        $aceptadas = $ofertas()->whereIn('estado', ['aceptada', 'cerrada', 'reemplazada'])->whereNotNull('respondida_en')->count();
        $respondidas = $aceptadas + $ofertas()->whereIn('estado', ['rechazada', 'vencida'])->count();
        $cancelaciones = DB::table('neg_cancelaciones')->whereNull('deleted_at')->where('parte', 'proveedor')->whereBetween('created_at', [$desde, $hasta])->count();

        return [
            'proveedores_aprobados' => DB::table('prv_proveedores')->whereNull('deleted_at')->where('estado', 'aprobado')->count(),
            'proveedores_pendientes' => DB::table('prv_proveedores')->whereNull('deleted_at')->where('estado', 'pendiente')->count(),
            'drones_aprobados' => DB::table('flo_drones')->whereNull('deleted_at')->where('estado', 'aprobado')->count(),
            'ofertas_respondidas' => $respondidas,
            'tasa_aceptacion' => Razones::tasa($aceptadas, $respondidas),
            'cancelaciones_proveedor' => $cancelaciones,
            'tasa_cancelacion' => Razones::tasa($cancelaciones, $aceptadas),
        ];
    }

    /** @return array<string, mixed> */
    private function ingreso(Carbon $desde, Carbon $hasta): array
    {
        $completadas = DB::table('sol_solicitud_historial')->whereNull('deleted_at')->where('estado_nuevo', 'finalizada')->whereBetween('created_at', [$desde, $hasta])->pluck('solicitud_id')->unique()->all();

        $filas = $completadas === []
            ? collect()
            : DB::table('sol_solicitudes_servicio')->whereNull('deleted_at')->whereIn('id', $completadas)->get(['monto_final', 'hectareas_ejecutadas']);

        $volumen = Razones::suma(...$filas->pluck('monto_final')->all());
        $hectareas = (string) BigDecimal::of(Razones::suma(...$filas->pluck('hectareas_ejecutadas')->all()))->toScale(2);

        return [
            'servicios_completados' => count($completadas),
            'volumen_bruto' => $volumen,
            'ingreso_por_hectarea' => Razones::cociente($volumen, $hectareas),
        ];
    }

    /**
     * Una fila por proveedor con servicios completados en el período, de mayor a menor.
     *
     * @return list<array<string, mixed>>
     */
    private function porProveedor(Carbon $desde, Carbon $hasta): array
    {
        $completadas = DB::table('sol_solicitud_historial')->whereNull('deleted_at')->where('estado_nuevo', 'finalizada')->whereBetween('created_at', [$desde, $hasta])->pluck('solicitud_id')->unique()->all();

        if ($completadas === []) {
            return [];
        }

        $filas = DB::table('sol_solicitudes_servicio')
            ->whereNull('deleted_at')
            ->whereIn('id', $completadas)
            ->whereNotNull('proveedor_id')
            ->selectRaw('proveedor_id, COUNT(*) as servicios')
            ->groupBy('proveedor_id')
            ->orderByDesc('servicios')
            ->limit(self::PROVEEDORES_EN_TABLA)
            ->get();

        $respuestas = DB::table('neg_ofertas')
            ->whereNull('deleted_at')
            ->where('autor', 'cliente')
            ->whereNotNull('respondida_en')
            ->whereIn('proveedor_id', $filas->pluck('proveedor_id')->all())
            ->whereBetween('created_at', [$desde, $hasta])
            ->limit(self::LIMITE_FILAS)
            ->get(['proveedor_id', 'created_at as ofrecida_en', 'respondida_en'])
            ->groupBy('proveedor_id');

        return $filas->map(function ($fila) use ($respuestas): array {
            $datos = $this->proveedores->proveedor((int) $fila->proveedor_id);
            $minutos = collect($respuestas[$fila->proveedor_id] ?? [])
                ->filter(fn ($oferta): bool => $oferta->respondida_en !== null)
                ->map(fn ($oferta): float => max(0, Carbon::parse($oferta->ofrecida_en)->diffInSeconds(Carbon::parse($oferta->respondida_en), false)) / 60)
                ->values()
                ->all();

            return [
                'proveedor_id' => (int) $fila->proveedor_id,
                'nombre' => $datos === null ? "#{$fila->proveedor_id}" : $datos->razonSocial,
                'tipo' => $datos === null ? '' : $datos->tipo,
                'servicios' => (int) $fila->servicios,
                'calificacion_promedio' => $datos === null ? null : $datos->calificacionPromedio,
                'calificaciones_conteo' => $datos === null ? 0 : $datos->calificacionesConteo,
                'tasa_aceptacion' => $datos === null ? null : $datos->tasaAceptacion,
                'respuesta_promedio_min' => Razones::promedio($minutos),
            ];
        })->all();
    }

    /**
     * Reembolso y ajuste de comisión de las disputas resueltas en el período, contando
     * solo la última resolución de cada una (la apelación reemplaza a la primera).
     *
     * @return array{monto: string, ajuste_comision: string}
     */
    private function reembolsos(Carbon $desde, Carbon $hasta): array
    {
        $ultimas = DB::table('dsp_resoluciones')
            ->whereNull('deleted_at')
            ->whereBetween('resuelta_en', [$desde, $hasta])
            ->orderBy('disputa_id')
            ->orderBy('ronda')
            ->limit(self::LIMITE_FILAS)
            ->get(['disputa_id', 'monto_reembolso', 'comision_ajuste'])
            ->keyBy('disputa_id');

        return [
            'monto' => Razones::suma(...$ultimas->pluck('monto_reembolso')->all()),
            'ajuste_comision' => Razones::suma(...$ultimas->pluck('comision_ajuste')->all()),
        ];
    }

    private function suma(Builder $consulta, string $columna, int $escala): string
    {
        return (string) BigDecimal::of((string) $consulta->sum($columna))->toScale($escala, RoundingMode::HalfUp);
    }
}
