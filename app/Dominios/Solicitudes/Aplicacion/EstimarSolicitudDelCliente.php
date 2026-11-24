<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Compartido\Dominio\MontoDelServicio;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;

/**
 * Monto estimado de una solicitud propia (`GET /solicitudes/{id}/estimacion`):
 * hectáreas solicitadas por el precio por hectárea vigente, que es el acordado
 * una vez adjudicada y el propuesto antes. Sin recargos ni tarifa: el precio lo
 * pone la negociación. Importes como decimal exacto en string.
 */
final class EstimarSolicitudDelCliente
{
    public function __construct(private readonly ObtenerSolicitudDelCliente $solicitudes) {}

    /**
     * @return array{precio_ha: string|null, precio_acordado: bool, hectareas: string, monto_estimado: string|null, precio_referencia_ha: string|null}
     *
     * @throws SolicitudInvalida
     */
    public function ejecutar(int $usuarioId, int $solicitudId): array
    {
        $solicitud = $this->solicitudes->ejecutar($usuarioId, $solicitudId);

        $precio = $solicitud->precio_ha_acordado ?? $solicitud->precio_ha_propuesto;

        return [
            'precio_ha' => $precio === null ? null : (string) $precio,
            'precio_acordado' => $solicitud->precio_ha_acordado !== null,
            'hectareas' => $solicitud->hectareas_solicitadas,
            'monto_estimado' => $precio === null ? null : MontoDelServicio::monto($solicitud->hectareas_solicitadas, (string) $precio),
            'precio_referencia_ha' => config('negociacion.precio_referencia_ha.'.$solicitud->tipo_aplicacion->value),
        ];
    }
}
