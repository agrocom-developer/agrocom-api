<?php

namespace App\Dominios\Metricas\Infraestructura\Lectura;

use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use Illuminate\Support\Carbon;

/** Los extremos de un período como los espera `whereBetween`, y los números que devuelve la base como cadena exacta. */
final class VentanaSql
{
    /** Tope de filas al promediar tiempos: acota la memoria de un período con mucho movimiento. */
    public const LIMITE_FILAS = 5000;

    /** @return array{0: Carbon, 1: Carbon} */
    public static function de(PeriodoDeMetricas $periodo): array
    {
        return [Carbon::instance($periodo->desde), Carbon::instance($periodo->hasta)];
    }
}
