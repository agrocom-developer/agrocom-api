<?php

namespace App\Dominios\Metricas\Aplicacion\Bloques;

use App\Dominios\Metricas\Contratos\LecturaMetricasMercado;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use App\Dominios\Metricas\Dominio\Razones;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Bloque «mercado»: solicitudes, adjudicación, tiempo hasta adjudicar y rondas de negociación. */
final class BloqueMercado
{
    public function __construct(private readonly LecturaMetricasMercado $lectura) {}

    /** @return array<string, mixed> */
    public function armar(PeriodoDeMetricas $periodo): array
    {
        $solicitudes = $this->lectura->solicitudes($periodo);
        $minutos = $this->lectura->minutosHastaAdjudicacion($periodo);
        $negociaciones = $this->lectura->negociaciones($periodo);
        $total = $solicitudes['total'];

        return [
            'solicitudes_publicadas' => $total,
            'por_estado' => $solicitudes['por_estado'],
            'por_departamento' => $solicitudes['por_departamento'],
            'adjudicadas' => $solicitudes['adjudicadas'],
            'tasa_adjudicacion' => Razones::tasa($solicitudes['adjudicadas'], $total),
            'expiradas' => (int) ($solicitudes['por_estado']['expirada'] ?? 0),
            'hectareas_solicitadas' => $solicitudes['hectareas_solicitadas'],
            'hectareas_servidas' => $solicitudes['hectareas_servidas'],
            'tiempo_aceptacion_promedio_min' => Razones::promedio($minutos),
            'tiempo_aceptacion_mediana_min' => Razones::mediana($minutos),
            'aceptaciones' => count($minutos),
            'ofertas_por_solicitud' => $total === 0 ? null : (string) BigDecimal::of($negociaciones['ofertas'])->dividedBy($total, 2, RoundingMode::HalfUp),
            'rondas_promedio' => Razones::promedio($negociaciones['rondas']),
            'negociaciones' => count($negociaciones['rondas']),
        ];
    }
}
