<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudPublicada;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Support\Carbon;

/**
 * Publica una solicitud (`POST /solicitudes/{id}/publicar`, especificación
 * §10.1): congela la tarifa por hectárea y el monto estimado y la deja
 * `publicada` para que el motor de asignación la tome. También republica una
 * `sin_proveedor` (el cliente amplía la ventana): la tarifa ya congelada no
 * cambia (política de comisión: cambiarla exige aceptación expresa del cliente).
 */
final class PublicarSolicitud
{
    public function __construct(
        private readonly ClienteDeLaCuenta $clientes,
        private readonly ObtenerSolicitudDelCliente $solicitudes,
        private readonly EstimarTarifa $tarifa,
        private readonly MaquinaEstadosSolicitud $estados,
    ) {}

    /**
     * @param  string|null  $ventanaHasta  nueva fecha límite (solo al republicar una `sin_proveedor`).
     *
     * @throws SolicitudInvalida
     * @throws TransicionSolicitudNoPermitida
     */
    public function ejecutar(int $usuarioId, int $solicitudId, ?string $ventanaHasta = null): SolicitudServicio
    {
        $this->clientes->activo($usuarioId);
        $solicitud = $this->solicitudes->ejecutar($usuarioId, $solicitudId);
        $republica = $solicitud->estado === EstadoSolicitud::SinProveedor;

        $cambios = ['publicada_en' => Carbon::now()];

        if ($ventanaHasta !== null) {
            if (! $republica) {
                throw SolicitudInvalida::noSePuedeEditar();
            }
            if (Carbon::parse($ventanaHasta)->startOfDay()->lt(Carbon::parse($solicitud->ventana_desde)->startOfDay())) {
                throw SolicitudInvalida::ventanaVencida();
            }
            $cambios['ventana_hasta'] = $ventanaHasta;
        }

        $hasta = Carbon::parse($cambios['ventana_hasta'] ?? $solicitud->ventana_hasta);
        if ($hasta->startOfDay()->lt(Carbon::today())) {
            throw SolicitudInvalida::ventanaVencida();
        }

        if (! $republica) {
            $estimacion = $this->tarifa->ejecutar(
                $solicitud->tipo_aplicacion,
                $solicitud->hectareas_solicitadas,
                $solicitud->urgente,
                $solicitud->terreno->acceso_dificil,
                $solicitud->aporta_producto,
            );
            $cambios['tarifa_hectarea'] = $estimacion->tarifaHectarea;
            $cambios['monto_estimado'] = $estimacion->montoEstimado;
        }

        $publicada = $this->estados->transicionar(
            $solicitud,
            EstadoSolicitud::Publicada,
            ActorSolicitud::Cliente,
            $usuarioId,
            null,
            $cambios,
        );

        event(new SolicitudPublicada(
            $publicada->id,
            $publicada->cliente_id,
            $publicada->hectareas_solicitadas,
            $publicada->urgente,
            $republica,
        ));

        return $publicada->load(['terreno', 'historial']);
    }
}
