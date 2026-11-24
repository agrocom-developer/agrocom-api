<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudEjecutada;
use InvalidArgumentException;

/**
 * El servicio quedó ejecutado y falta la conformidad: le importa al cliente, que tiene un plazo para darla o abrir una disputa (especificación §12). La clave es la solicitud: se ejecuta una sola vez.
 */
final class ReglaSolicitudEjecutada implements ReglaNotificacion
{
    public function evento(): string
    {
        return SolicitudEjecutada::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof SolicitudEjecutada) {
            throw new InvalidArgumentException('ReglaSolicitudEjecutada solo atiende SolicitudEjecutada.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::SolicitudEjecutada,
            claveEvento: NotificacionArmada::claveDe(TipoNotificacion::SolicitudEjecutada, $evento->solicitudId),
            parametros: ['solicitud' => $evento->solicitudId],
            recurso: RecursoNotificable::Solicitud,
            recursoId: $evento->solicitudId,
            destinatarios: [Destinatario::usuario($evento->clienteUsuarioId)],
        );
    }
}
