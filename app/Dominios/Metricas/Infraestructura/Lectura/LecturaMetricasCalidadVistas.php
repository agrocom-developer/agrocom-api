<?php

namespace App\Dominios\Metricas\Infraestructura\Lectura;

use App\Dominios\Metricas\Contratos\LecturaMetricasCalidad;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use App\Dominios\Metricas\Dominio\Razones;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Cifras de calidad leídas de `vw_metricas_finalizaciones`, `_solicitudes`, `_calificaciones`, `_reclamos` y `_resoluciones`. */
final class LecturaMetricasCalidadVistas implements LecturaMetricasCalidad
{
    public function serviciosEjecutados(PeriodoDeMetricas $periodo): int
    {
        return $this->finalizadas($periodo)->distinct()->count('solicitud_id');
    }

    public function montoDeServiciosEjecutados(PeriodoDeMetricas $periodo): string
    {
        return Razones::escalar(
            DB::table('vw_metricas_solicitudes')->whereIn('solicitud_id', $this->finalizadas($periodo)->select('solicitud_id'))->sum('monto_final'),
            2,
        );
    }

    public function calificaciones(PeriodoDeMetricas $periodo): array
    {
        $consulta = DB::table('vw_metricas_calificaciones')
            ->whereRaw('es_de_cliente_a_proveedor')
            ->where('estado', '!=', 'oculta')
            ->whereBetween('calificada_en', VentanaSql::de($periodo));

        return ['conteo' => (clone $consulta)->count(), 'estrellas' => (int) $consulta->sum('estrellas')];
    }

    public function calificacionesReportadas(): int
    {
        return DB::table('vw_metricas_calificaciones')->where('estado', 'en_moderacion')->count();
    }

    public function reclamosPorEstado(PeriodoDeMetricas $periodo): array
    {
        return DB::table('vw_metricas_reclamos')
            ->whereBetween('abierto_en',VentanaSql::de($periodo))
            ->selectRaw('estado, COUNT(*) as cantidad')
            ->groupBy('estado')
            ->pluck('cantidad', 'estado')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    public function reembolsos(PeriodoDeMetricas $periodo): array
    {
        $ventana = VentanaSql::de($periodo);

        $ultimas = DB::table('vw_metricas_resoluciones as r')
            ->whereBetween('r.resuelta_en', $ventana)
            ->whereRaw(
                'r.ronda = (SELECT MAX(x.ronda) FROM vw_metricas_resoluciones x WHERE x.reclamo_id = r.reclamo_id AND x.resuelta_en BETWEEN ? AND ?)',
                $ventana,
            );

        return [
            'monto' => Razones::escalar((clone $ultimas)->sum('r.monto_reembolso'), 2),
            'ajuste_comision' => Razones::escalar($ultimas->sum('r.comision_ajuste'), 2),
        ];
    }

    private function finalizadas(PeriodoDeMetricas $periodo): Builder
    {
        return DB::table('vw_metricas_finalizaciones')->whereBetween('finalizada_en', VentanaSql::de($periodo));
    }
}
