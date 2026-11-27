<?php

namespace App\Dominios\Metricas\Contratos;

use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;

/** Lectura de las cifras de calidad de un período: servicios ejecutados, calificaciones, reclamos y reembolsos. */
interface LecturaMetricasCalidad
{
    /** @return int solicitudes distintas que llegaron a `finalizada` en el período. */
    public function serviciosEjecutados(PeriodoDeMetricas $periodo): int;

    /** @return string suma del monto final de esas solicitudes, DECIMAL a dos decimales. */
    public function montoDeServiciosEjecutados(PeriodoDeMetricas $periodo): string;

    /** @return array{conteo: int, estrellas: int} calificaciones del cliente al proveedor no ocultas. */
    public function calificaciones(PeriodoDeMetricas $periodo): array;

    /** @return int calificaciones en moderación (sin acotar por período: es una bandeja). */
    public function calificacionesReportadas(): int;

    /** @return array<string, int> reclamos abiertos en el período, por estado. */
    public function reclamosPorEstado(PeriodoDeMetricas $periodo): array;

    /**
     * Solo la última resolución de cada reclamo resuelto en el período (la apelación reemplaza a la primera).
     *
     * @return array{monto: string, ajuste_comision: string}
     */
    public function reembolsos(PeriodoDeMetricas $periodo): array;
}
