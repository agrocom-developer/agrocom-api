<?php

namespace App\Dominios\Metricas\Aplicacion\Bloques;

use App\Dominios\Metricas\Contratos\LecturaMetricasIngreso;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use App\Dominios\Metricas\Dominio\Razones;

/** Bloque «ingreso»: servicios completados, volumen bruto e ingreso por hectárea. */
final class BloqueIngreso
{
    public function __construct(private readonly LecturaMetricasIngreso $lectura) {}

    /** @return array<string, mixed> */
    public function armar(PeriodoDeMetricas $periodo): array
    {
        $completados = $this->lectura->completados($periodo);

        return [
            'servicios_completados' => $completados['servicios'],
            'volumen_bruto' => $completados['monto'],
            'ingreso_por_hectarea' => Razones::cociente($completados['monto'], $completados['hectareas']),
        ];
    }
}
