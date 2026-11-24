<?php

namespace App\Dominios\Metricas\Contratos;

use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;

/** Lectura del volumen de servicios finalizados en un período y de su reparto por proveedor. */
interface LecturaMetricasIngreso
{
    /** @return array{servicios: int, monto: string, hectareas: string} monto y hectáreas DECIMAL a dos decimales. */
    public function completados(PeriodoDeMetricas $periodo): array;

    /**
     * Proveedores con servicios completados en el período, de mayor a menor.
     *
     * @return list<array{proveedor_id: int, servicios: int}>
     */
    public function serviciosPorProveedor(PeriodoDeMetricas $periodo, int $limite): array;

    /**
     * Minutos que cada proveedor tardó en responder las ofertas del cliente, creadas en el período.
     *
     * @param  list<int>  $proveedores
     * @return array<int, list<float>> por id de proveedor
     */
    public function minutosDeRespuesta(PeriodoDeMetricas $periodo, array $proveedores): array;
}
