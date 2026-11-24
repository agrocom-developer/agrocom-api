<?php

namespace App\Dominios\Solicitudes\Dominio;

/**
 * Resultado de estimar una solicitud: tarifa base por hectárea, cada recargo
 * por separado (el cliente los ve explícitos antes de confirmar, política de
 * comisión) y el total. Todo en cadenas decimales exactas (invariante 6).
 */
final readonly class DatosEstimacion
{
    public function __construct(
        /** Tarifa base por hectárea según el tipo de aplicación. */
        public string $tarifaBaseHectarea,
        /** Tarifa por hectárea ya con los recargos por hectárea (se congela al publicar). */
        public string $tarifaHectarea,
        public string $hectareas,
        public string $subtotalBase,
        public string $recargoUrgente,
        public string $recargoAccesoDificil,
        public string $recargoProducto,
        public string $montoEstimado,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'tarifa_base_hectarea' => $this->tarifaBaseHectarea,
            'tarifa_hectarea' => $this->tarifaHectarea,
            'hectareas' => $this->hectareas,
            'subtotal_base' => $this->subtotalBase,
            'recargo_urgente' => $this->recargoUrgente,
            'recargo_acceso_dificil' => $this->recargoAccesoDificil,
            'recargo_producto' => $this->recargoProducto,
            'monto_estimado' => $this->montoEstimado,
        ];
    }
}
