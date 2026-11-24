<?php

namespace App\Dominios\Notificaciones\Aplicacion\Reglas;

use App\Dominios\Asignacion\Contratos\Eventos\SolicitudReasignada;
use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Notificaciones\Dominio\NotificacionArmada;
use App\Dominios\Notificaciones\Dominio\RecursoNotificable;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use App\Dominios\Notificaciones\Dominio\RolDestinatario;
use App\Dominios\Notificaciones\Dominio\TipoNotificacion;
use InvalidArgumentException;

/**
 * El proveedor canceló un servicio ya aceptado y la solicitud vuelve a asignación con prioridad: se le avisa al cliente y al equipo de operaciones, que sigue la reasignación.
 */
final class ReglaSolicitudReasignada implements ReglaNotificacion
{
    public function evento(): string
    {
        return SolicitudReasignada::class;
    }

    public function armar(object $evento): NotificacionArmada
    {
        if (! $evento instanceof SolicitudReasignada) {
            throw new InvalidArgumentException('ReglaSolicitudReasignada solo atiende SolicitudReasignada.');
        }

        return new NotificacionArmada(
            tipo: TipoNotificacion::SolicitudReasignada,
            claveEvento: "solicitud_reasignada:{$evento->solicitudId}:{$evento->ciclo}",
            parametros: [
                'solicitud' => $evento->solicitudId,
            ],
            recurso: RecursoNotificable::Solicitud,
            recursoId: $evento->solicitudId,
            destinatarios: [Destinatario::usuario($evento->clienteUsuarioId), Destinatario::rol(RolDestinatario::EncargadoOperaciones)],
        );
    }
}
