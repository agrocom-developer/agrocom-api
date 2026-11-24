<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudCompletada;
use InvalidArgumentException;

/**
 * Un servicio completado le importa al cliente, que ya puede calificarlo, y al proveedor, que ya puede calificar al cliente (especificación §12, SolicitudCompletada). La clave es la solicitud: se completa una sola vez.
 */
final class ReglaSolicitudCompletada implements ReglaNotificacion
{
    public function evento(): string
    {
        return SolicitudCompletada::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof SolicitudCompletada) {
            throw new InvalidArgumentException('ReglaSolicitudCompletada solo atiende SolicitudCompletada.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::SolicitudCompletada,
            claveEvento: NotificacionArmada::claveDe(TipoNotificacion::SolicitudCompletada, $evento->solicitudId),
            parametros: ['solicitud' => $evento->solicitudId],
            recurso: RecursoNotificable::Solicitud,
            recursoId: $evento->solicitudId,
            destinatarios: array_map(
                static fn (int $usuarioId): Destinatario => Destinatario::usuario($usuarioId),
                array_values(array_unique([$evento->clienteUsuarioId, ...$evento->usuariosProveedor])),
            ),
        );
    }
}
