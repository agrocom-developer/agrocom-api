<?php

namespace App\Dominios\Metricas\Aplicacion\Bloques;

use App\Dominios\Metricas\Contratos\LecturaMetricasCalidad;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use App\Dominios\Metricas\Dominio\Razones;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Bloque «calidad»: calificación, disputas y reembolsos. */
final class BloqueCalidad
{
    public function __construct(private readonly LecturaMetricasCalidad $lectura) {}

    /** @return array<string, mixed> */
    public function armar(PeriodoDeMetricas $periodo): array
    {
        $ejecutados = $this->lectura->serviciosEjecutados($periodo);
        $calificaciones = $this->lectura->calificaciones($periodo);
        $disputas = $this->lectura->disputasPorEstado($periodo);
        $abiertas = array_sum($disputas);
        $reembolsado = $this->lectura->reembolsos($periodo);

        return [
            'calificacion_promedio' => $calificaciones['conteo'] === 0 ? null : (string) BigDecimal::of($calificaciones['estrellas'])->dividedBy($calificaciones['conteo'], 2, RoundingMode::HalfUp),
            'calificaciones_conteo' => $calificaciones['conteo'],
            'calificaciones_reportadas' => $this->lectura->calificacionesReportadas(),
            'servicios_ejecutados' => $ejecutados,
            'disputas_abiertas' => $abiertas,
            'disputas_por_estado' => $disputas,
            'tasa_disputas' => Razones::tasa($abiertas, $ejecutados),
            'monto_reembolsado' => $reembolsado['monto'],
            'tasa_reembolso' => Razones::tasaDeImportes($reembolsado['monto'], $this->lectura->montoDeServiciosEjecutados($periodo)),
        ];
    }
}
