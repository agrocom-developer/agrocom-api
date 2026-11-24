<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;

/**
 * Tarifa estimada de una solicitud del cliente (`GET /solicitudes/{id}/estimacion`).
 * Antes de publicar es una estimación con la tarifa vigente y el desglose de
 * cada recargo; ya publicada, es la tarifa congelada (sin desglose): el cliente
 * ve siempre lo que de verdad va a pagar y nunca cambia después de publicar.
 */
final class EstimarSolicitudDelCliente
{
    public function __construct(
        private readonly ObtenerSolicitudDelCliente $solicitudes,
        private readonly EstimarTarifa $tarifa,
    ) {}

    /**
     * @return array<string, string|bool|null>
     *
     * @throws SolicitudInvalida
     */
    public function ejecutar(int $usuarioId, int $solicitudId): array
    {
        $solicitud = $this->solicitudes->ejecutar($usuarioId, $solicitudId);

        if ($solicitud->tarifa_hectarea !== null && $solicitud->monto_estimado !== null) {
            return [
                'congelada' => true,
                'tarifa_base_hectarea' => null,
                'tarifa_hectarea' => $solicitud->tarifa_hectarea,
                'hectareas' => $solicitud->hectareas_solicitadas,
                'subtotal_base' => null,
                'recargo_urgente' => null,
                'recargo_acceso_dificil' => null,
                'recargo_producto' => null,
                'monto_estimado' => $solicitud->monto_estimado,
            ];
        }

        $estimacion = $this->tarifa->ejecutar(
            $solicitud->tipo_aplicacion,
            $solicitud->hectareas_solicitadas,
            $solicitud->urgente,
            $solicitud->terreno->acceso_dificil,
            $solicitud->aporta_producto,
        );

        return ['congelada' => false, ...$estimacion->toArray()];
    }
}
