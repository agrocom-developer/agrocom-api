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
        $porDepartamento = $publicadas()->selectRaw("COALESCE(departamento_codigo, 'sin_departamento') as departamento, COUNT(*) as cantidad")->groupBy('departamento_codigo')->pluck('cantidad', 'departamento')->map(fn ($n): int => (int) $n)->all();

        return [
            'total' => array_sum($porEstado),
            'adjudicadas' => $publicadas()->whereNotNull('proveedor_id')->count(),
            'por_estado' => $porEstado,
            'por_departamento' => $porDepartamento,
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

    public function conOfertas(PeriodoDeMetricas $periodo): int
    {
        $publicadas = DB::table('vw_metricas_solicitudes')->whereBetween('publicada_en', VentanaSql::de($periodo))->select('solicitud_id');

        return DB::table('vw_metricas_negociaciones')->whereIn('solicitud_id', $publicadas)->distinct()->count('solicitud_id');
    }

    public function porDepartamentoYEstado(PeriodoDeMetricas $periodo): array
    {
        return DB::table('vw_metricas_solicitudes as v')
            ->leftJoin('sol_departamentos as d', 'd.codigo', '=', 'v.departamento_codigo')
            ->whereBetween('v.publicada_en', VentanaSql::de($periodo))
            ->selectRaw("COALESCE(v.departamento_codigo, 'sin_departamento') as codigo, d.nombre as nombre, v.estado as estado, COUNT(*) as cantidad")
            ->groupBy('v.departamento_codigo', 'd.nombre', 'v.estado')
            ->get()
            ->map(fn (object $fila): array => ['codigo' => (string) $fila->codigo, 'nombre' => $fila->nombre === null ? null : (string) $fila->nombre, 'estado' => (string) $fila->estado, 'cantidad' => (int) $fila->cantidad])
            ->all();
    }
}
