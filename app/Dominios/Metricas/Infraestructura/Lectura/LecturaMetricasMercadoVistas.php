<?php

namespace App\Dominios\Metricas\Infraestructura\Lectura;

use App\Dominios\Metricas\Contratos\LecturaMetricasMercado;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use App\Dominios\Metricas\Dominio\Razones;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Cifras de mercado leídas de `vw_metricas_solicitudes`, `_aceptaciones` y `_negociaciones`. */
final class LecturaMetricasMercadoVistas implements LecturaMetricasMercado
{
    public function solicitudes(PeriodoDeMetricas $periodo): array
    {
        $publicadas = fn (): Builder => DB::table('vw_metricas_solicitudes')->whereBetween('publicada_en', VentanaSql::de($periodo));

        $porEstado = $publicadas()->selectRaw('estado, COUNT(*) as cantidad')->groupBy('estado')->pluck('cantidad', 'estado')->map(fn ($n): int => (int) $n)->all();
        $porRegion = $publicadas()->selectRaw('region, COUNT(*) as cantidad')->groupBy('region')->pluck('cantidad', 'region')->map(fn ($n): int => (int) $n)->all();

        return [
            'total' => array_sum($porEstado),
            'adjudicadas' => $publicadas()->whereNotNull('proveedor_id')->count(),
            'por_estado' => $porEstado,
            'por_region' => $porRegion,
            'hectareas_solicitadas' => Razones::escalar($publicadas()->sum('hectareas_solicitadas'), 4),
            'hectareas_servidas' => Razones::escalar($publicadas()->whereIn('estado', ['finalizada', 'calificada'])->sum('hectareas_ejecutadas'), 4),
        ];
    }

    public function minutosHastaAdjudicacion(PeriodoDeMetricas $periodo): array
    {
        return DB::table('vw_metricas_aceptaciones')
            ->whereBetween('adjudicada_en', VentanaSql::de($periodo))
            ->orderBy('oferta_id')
            ->limit(VentanaSql::LIMITE_FILAS)
            ->pluck('minutos')
            ->map(fn ($minutos): float => (float) $minutos)
            ->all();
    }

    public function negociaciones(PeriodoDeMetricas $periodo): array
    {
        $publicadas = DB::table('vw_metricas_solicitudes')->whereBetween('publicada_en', VentanaSql::de($periodo))->select('solicitud_id');
        $pares = fn (): Builder => DB::table('vw_metricas_negociaciones')->whereIn('solicitud_id', $publicadas);

        return [
            'ofertas' => (int) $pares()->sum('ofertas'),
            'rondas' => $pares()->pluck('rondas')->map(fn ($n): int => (int) $n)->all(),
        ];
    }
}
