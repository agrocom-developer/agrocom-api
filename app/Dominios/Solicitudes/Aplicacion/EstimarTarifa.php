<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Dominio\AportaProducto;
use App\Dominios\Solicitudes\Dominio\DatosEstimacion;
use App\Dominios\Solicitudes\Dominio\EstimacionTarifa;
use App\Dominios\Solicitudes\Dominio\TipoAplicacionServicio;

/**
 * Arma la {@see EstimacionTarifa} con los parámetros de `config/solicitudes.php`
 * (ADR 0016): el único lugar donde la configuración entra a la tarifa.
 */
final class EstimarTarifa
{
    public function ejecutar(
        TipoAplicacionServicio $tipo,
        string $hectareas,
        bool $urgente,
        bool $accesoDificil,
        AportaProducto $aportaProducto,
    ): DatosEstimacion {
        $tarifa = new EstimacionTarifa(
            tarifasPorTipo: array_map('strval', (array) config('solicitudes.tarifa_hectarea')),
            recargoUrgentePct: (string) config('solicitudes.recargo_urgente_pct'),
            recargoAccesoDificilPct: (string) config('solicitudes.recargo_acceso_dificil_pct'),
            recargoProductoHectarea: (string) config('solicitudes.recargo_producto_proveedor_hectarea'),
        );

        return $tarifa->estimar($tipo, $hectareas, $urgente, $accesoDificil, $aportaProducto);
    }
}
