<?php

namespace App\Dominios\Metricas\Aplicacion\Bloques;

use App\Dominios\Metricas\Contratos\LecturaMetricasOferta;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use App\Dominios\Metricas\Dominio\Razones;

/** Bloque «oferta»: proveedores y drones, tasa de aceptación y de cancelación. */
final class BloqueOferta
{
    public function __construct(private readonly LecturaMetricasOferta $lectura) {}

    /** @return array<string, mixed> */
    public function armar(PeriodoDeMetricas $periodo): array
    {
        $aceptadas = $this->lectura->ofertasAceptadas($periodo);
        $respondidas = $aceptadas + $this->lectura->ofertasNoAceptadas($periodo);
        $cancelaciones = $this->lectura->cancelacionesDelProveedor($periodo);

        return [
            'proveedores_aprobados' => $this->lectura->proveedoresEn('aprobado'),
            'proveedores_pendientes' => $this->lectura->proveedoresEn('pendiente'),
            'drones_aprobados' => $this->lectura->dronesEn('aprobado'),
            'ofertas_respondidas' => $respondidas,
            'tasa_aceptacion' => Razones::tasa($aceptadas, $respondidas),
            'cancelaciones_proveedor' => $cancelaciones,
            'tasa_cancelacion' => Razones::tasa($cancelaciones, $aceptadas),
            'sanciones_vigentes' => 0,
        ];
    }
}
