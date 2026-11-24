<?php

namespace App\Dominios\Metricas\Infraestructura\Lectura;

use App\Dominios\Metricas\Contratos\LecturaMetricasIngreso;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use App\Dominios\Metricas\Dominio\Razones;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Volumen y reparto por proveedor leídos de `vw_metricas_finalizaciones`, `_solicitudes` y `_respuestas_proveedor`. */
final class LecturaMetricasIngresoVistas implements LecturaMetricasIngreso
{
    public function completados(PeriodoDeMetricas $periodo): array
    {
        $solicitudes = $this->solicitudesCompletadas($periodo);

        return [
            'servicios' => DB::table('vw_metricas_finalizaciones')->whereBetween('finalizada_en', VentanaSql::de($periodo))->distinct()->count('solicitud_id'),
            'monto' => Razones::escalar((clone $solicitudes)->sum('monto_final'), 2),
            'hectareas' => Razones::escalar($solicitudes->sum('hectareas_ejecutadas'), 2),
        ];
    }

    public function serviciosPorProveedor(PeriodoDeMetricas $periodo, int $limite): array
    {
        return $this->solicitudesCompletadas($periodo)
            ->whereNotNull('proveedor_id')
            ->selectRaw('proveedor_id, COUNT(*) as servicios')
            ->groupBy('proveedor_id')
            ->orderByDesc('servicios')
            ->orderBy('proveedor_id')
            ->limit($limite)
            ->get()
            ->map(fn ($fila): array => ['proveedor_id' => (int) $fila->proveedor_id, 'servicios' => (int) $fila->servicios])
            ->all();
    }

    public function minutosDeRespuesta(PeriodoDeMetricas $periodo, array $proveedores): array
    {
        if ($proveedores === []) {
            return [];
        }

        return DB::table('vw_metricas_respuestas_proveedor')
            ->whereIn('proveedor_id', $proveedores)
            ->whereBetween('ofrecida_en', VentanaSql::de($periodo))
            ->limit(VentanaSql::LIMITE_FILAS)
            ->get(['proveedor_id', 'minutos'])
            ->groupBy('proveedor_id')
            ->map(fn ($filas): array => $filas->map(fn ($fila): float => (float) $fila->minutos)->values()->all())
            ->mapWithKeys(fn (array $minutos, $proveedor): array => [(int) $proveedor => $minutos])
            ->all();
    }

    /** Solicitudes vivas con un `finalizada` en el período, una fila por solicitud. */
    private function solicitudesCompletadas(PeriodoDeMetricas $periodo): Builder
    {
        $finalizadas = DB::table('vw_metricas_finalizaciones')->whereBetween('finalizada_en', VentanaSql::de($periodo))->select('solicitud_id');

        return DB::table('vw_metricas_solicitudes')->whereIn('solicitud_id', $finalizadas);
    }
}
