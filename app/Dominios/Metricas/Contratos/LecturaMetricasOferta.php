<?php

namespace App\Dominios\Metricas\Contratos;

use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;

/** Lectura de las cifras de oferta: proveedores y drones aprobados, ofertas respondidas y cancelaciones. */
interface LecturaMetricasOferta
{
    public function proveedoresEn(string $estado): int;

    public function dronesEn(string $estado): int;

    /** @return int ofertas del proveedor aceptadas (con respuesta) en el período. */
    public function ofertasAceptadas(PeriodoDeMetricas $periodo): int;

    /** @return int ofertas del proveedor rechazadas o vencidas en el período. */
    public function ofertasNoAceptadas(PeriodoDeMetricas $periodo): int;

    public function cancelacionesDelProveedor(PeriodoDeMetricas $periodo): int;
}
