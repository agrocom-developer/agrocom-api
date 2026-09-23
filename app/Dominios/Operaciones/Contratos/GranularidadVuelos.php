<?php

namespace App\Dominios\Operaciones\Contratos;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Con qué granularidad se agrupan las hectáreas de los vuelos: por día, por
 * semana (de lunes a domingo) o por mes calendario (tarea 136). Vive en
 * `Contratos/` porque quien elige la granularidad es el dashboard
 * (`Seguridad`) y quien agrupa es Operaciones — entre módulos solo se viaja
 * por contratos (ADR 0003).
 *
 * Solo resuelve dónde empieza cada período: la suma la hace
 * {@see LecturaPanelOperaciones::hectareasPorPeriodo()}, con el mismo query
 * para las tres. Cada período se identifica por su fecha de inicio.
 */
enum GranularidadVuelos: string
{
    case Dia = 'dia';
    case Semana = 'semana';
    case Mes = 'mes';

    /** Primer día del período que contiene a `$fecha`, a las 00:00. */
    public function inicioDelPeriodo(CarbonInterface $fecha): Carbon
    {
        $inicio = Carbon::instance($fecha)->startOfDay();

        return match ($this) {
            self::Dia => $inicio,
            self::Semana => $inicio->startOfWeek(CarbonInterface::MONDAY),
            self::Mes => $inicio->startOfMonth(),
        };
    }

    /**
     * El inicio de un período que dista `$periodos` del de `$inicio` (negativo
     * para los anteriores). `$inicio` ya tiene que ser un inicio de período.
     */
    public function desplazar(Carbon $inicio, int $periodos): Carbon
    {
        return match ($this) {
            self::Dia => $inicio->copy()->addDays($periodos),
            self::Semana => $inicio->copy()->addWeeks($periodos),
            self::Mes => $inicio->copy()->addMonthsNoOverflow($periodos),
        };
    }
}
